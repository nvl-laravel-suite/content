<?php

declare(strict_types=1);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\ContentBlockQueryScope;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentMutationMode;
use Nvl\Content\Enums\ContentPlacementEvent;
use Nvl\Content\Enums\ContentStatus;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Events\ContentPlacementChanged;
use Nvl\Content\Exceptions\ContentScopeOverflowException;
use Nvl\Content\Exceptions\StaleContentException;
use Nvl\Content\FieldTypes\ReferenceFieldTypeAdapter;
use Nvl\Content\FieldTypes\RichTextFieldTypeAdapter;
use Nvl\Content\FieldTypes\StringFieldTypeAdapter;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentBlockTranslation;
use Nvl\Content\Models\ContentDefinition;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Content\Relations\ExactTextValueComparison;
use Nvl\Content\Schema\ContentDefinitionSource;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDefinitionLoader;
use Nvl\Content\Services\ContentDefinitionRegistry;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentLocalizedValues;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentPayloadGuard;
use Nvl\Content\Services\ContentPlacements;
use Nvl\Content\Services\ContentReader;
use Nvl\Content\Services\ContentReferenceRegistry;
use Nvl\Content\Services\ContentRenderer;
use Nvl\Content\Services\ContentRenderResources;
use Nvl\Content\Services\ContentSnapshotService;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Tests\Fixtures\Consumer\Data\ContentPlacementData;
use Nvl\Content\Tests\Fixtures\Consumer\GetOwnerContentEditorAction;
use Nvl\Content\Tests\Fixtures\TestContentOwner;
use Nvl\Content\Tests\Fixtures\TestIntegerContentOwner;
use Nvl\Content\Tests\Fixtures\TestStringContentOwner;
use Nvl\Content\Validation\ContentValidationContext;
use Nvl\Content\Validation\ContentValueValidator;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;

beforeEach(function (): void {
    $this->contentContractMorphMap = Relation::morphMap();
    app(ContentDefinitions::class)->sync(SystemActor::Background);
});
afterEach(function (): void {
    Relation::morphMap($this->contentContractMorphMap, false);
});
it('preserves the original placement event constructor contract', function (): void {
    $event = new ContentPlacementChanged('placement-1', ContentPlacementEvent::Deleted, 2, SystemActor::Background);
    expect($event->ownerType)->toBeNull()->and($event->ownerId)->toBeNull()->and($event->group)->toBeNull()->and($event->blockId)->toBeNull();
});
it('rebuilds request-aware Content services between container scopes', function (): void {
    $content = app(ContentReader::class);
    $localizedValues = app(ContentLocalizedValues::class);
    app()->forgetScopedInstances();
    expect(app(ContentReader::class))->not->toBe($content)->and(app(ContentLocalizedValues::class))->not->toBe($localizedValues);
});
it('provides model-first grouped compositions through the facade and trait', function (): void {
    $actor = SystemActor::Background;
    $owner = TestContentOwner::query()->create(['name' => 'Grouped owner']);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'grouped-hero', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Grouped title']], 'metadata' => []], $actor);
    $block = app(ContentBlocks::class)->publish($block, $block->revision, $actor);
    $primaryPlacement = app(ContentPlacements::class)->place($block, $owner, 'primary', ['key' => 'hero', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    expect(fn () => app(ContentPlacements::class)->place($block, $owner, 'secondary', ['key' => 'child', 'region' => 'main', 'parentId' => $primaryPlacement->id, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor))->toThrow(InvalidArgumentException::class);
    app(ContentPlacements::class)->place($block, $owner, 'secondary', ['key' => 'hero', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $primary = app(ContentRenderer::class)->render($owner, ['group' => 'primary', 'locale' => 'en', 'actor' => $actor]);
    $snapshot = app(ContentSnapshotService::class)->capture($owner, 'secondary', $actor);
    $placements = app(ContentReader::class)->placements($owner, 'primary', $actor);
    expect($block)->toBeInstanceOf(ContentBlock::class)->and($primaryPlacement)->toBeInstanceOf(ContentPlacement::class)->and($owner->contentPlacements()->count())->toBe(2)->and($owner->contentPlacements()->first()?->owner)->toBeInstanceOf(TestContentOwner::class)->and(app(ContentReader::class)->groups($owner, $actor)->all())->toBe(['default', 'homepage', 'main', 'primary', 'secondary'])->and($placements)->toHaveCount(1)->and($placements->keys()->all())->toBe([0])->and($placements->first())->toBeInstanceOf(ContentPlacement::class)->and($placements->first()?->id)->toBe($primaryPlacement->id)->and($primary['group'])->toBe('primary')->and(contentRenderedValue($primary, 'hero.title', false))->toBe('Grouped title')->and($snapshot['group'])->toBe('secondary')->and(app(ContentSnapshotService::class)->render($snapshot, 'en', $actor)['group'])->toBe('secondary');
    expect(fn () => app(ContentRenderer::class)->render($owner, ['group' => 'undeclared', 'locale' => 'en', 'actor' => $actor]))->toThrow(InvalidArgumentException::class);
    $owner->delete();
    expect(ContentPlacement::query()->count())->toBe(0);
});
it('provides block reads through the same canonical application surface', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'canonical-query', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Canonical query']], 'metadata' => []], $actor);
    $read = app(ContentReader::class)->get($block->id, $actor);
    $blocks = app(ContentReader::class)->paginate([], $actor);
    expect($read)->toBeInstanceOf(ContentBlock::class)->and($read->id)->toBe($block->id)->and($read->translations->firstWhere('locale', 'en')?->values['title'] ?? null)->toBe('Canonical query')->and($blocks->total())->toBe(1)->and($blocks->items())->each->toBeInstanceOf(ContentBlock::class)->and($blocks->items()[0]->id)->toBe($block->id);
});
it('resolves complete localized content through ordered bounded scope fallback', function (): void {
    $actor = SystemActor::Background;
    $fallback = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'heading', 'scope' => 'site', 'scopeKey' => 'default', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Fallback heading'], 'bg' => ['title' => 'Заглавие по подразбиране']], 'metadata' => []], $actor);
    app(ContentBlocks::class)->publish($fallback, $fallback->revision, $actor);
    $specific = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'heading', 'scope' => 'site', 'scopeKey' => 'tenant-a', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Specific heading'], 'bg' => ['title' => 'Специфично заглавие']], 'metadata' => []], $actor);
    app(ContentBlocks::class)->publish($specific, $specific->revision, $actor);
    $private = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'private-note', 'scope' => 'site', 'scopeKey' => 'tenant-a', 'visibility' => ContentVisibility::Private, 'values' => [], 'translations' => ['en' => ['title' => 'Private note']], 'metadata' => []], $actor);
    app(ContentBlocks::class)->publish($private, $private->revision, $actor);
    app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'draft-note', 'scope' => 'site', 'scopeKey' => 'tenant-a', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Draft note']], 'metadata' => []], $actor);
    $scopes = [['scope' => 'site', 'scopeKey' => 'tenant-a'], ['scope' => 'site', 'scopeKey' => 'default']];
    $resolved = app(ContentReader::class)->resolveScopes($scopes, 'bg', $actor);
    $includingPrivate = app(ContentReader::class)->resolveScopes($scopes, 'en', $actor, publicOnly: false);
    expect($resolved['values'])->toHaveKeys(['heading'])->and($resolved['values']['heading']['title'])->toBe('Специфично заглавие')->and($resolved['sources'])->toBe(['heading' => 'site:tenant-a'])->and($resolved['matched'])->toBe(2)->and($includingPrivate['values'])->toHaveKeys(['heading', 'private-note'])->and(fn () => app(ContentReader::class)->resolveScopes($scopes, 'en', $actor, limit: 1))->toThrow(ContentScopeOverflowException::class);
    $invalidResolutions = [static fn () => app(ContentReader::class)->resolveScopes([], 'en', $actor), static fn () => app(ContentReader::class)->resolveScopes([['scope' => 'Invalid', 'scopeKey' => 'tenant-a']], 'en', $actor), static fn () => app(ContentReader::class)->resolveScopes([['scope' => 'site', 'scopeKey' => 'tenant-a'], ['scope' => 'site', 'scopeKey' => 'tenant-a']], 'en', $actor), static fn () => app(ContentReader::class)->resolveScopes($scopes, 'en', $actor, limit: 0)];
    foreach ($invalidResolutions as $invalidResolution) {
        expect($invalidResolution)->toThrow(InvalidArgumentException::class);
    }
});
it('returns one complete typed editor bootstrap for a consumer-owned UI', function (): void {
    $actor = SystemActor::Background;
    app(ContentDefinitionRegistry::class)->register(new ContentDefinitionSource(key: 'secondary-editor-schema', name: 'Secondary editor schema', description: null, category: 'content', version: 1, view: null, schema: ['fields' => []], allowedScopes: ['site'], allowedRegions: ['main'], sortOrder: 10));
    $owner = TestContentOwner::query()->create(['name' => 'Editor owner']);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'editor-bootstrap', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Editor bootstrap']], 'metadata' => []], $actor);
    $placement = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'hero', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $actionEditor = app(GetOwnerContentEditorAction::class)->execute($owner, 'homepage', $actor);
    $editor = app(GetOwnerContentEditorAction::class)->execute($owner, 'homepage', $actor);
    expect($editor['ownerType'])->toBe(TestContentOwner::class)->and($editor['ownerId'])->toBe($owner->id)->and($editor['group'])->toBe('homepage')->and($editor['placementLimit'])->toBe(1000)->and($editor['definitions'][0]['key'])->toBe('hero')->and(collect($editor['definitions'])->pluck('key')->all())->toBe(['hero', 'secondary-editor-schema'])->and(collect($editor['presets'])->pluck('alias')->all())->toBe(['banner', 'button', 'heading', 'image', 'link'])->and($editor['groups'])->toBe(['default', 'homepage', 'main', 'primary', 'secondary'])->and($editor['placements'])->toHaveCount(1)->and($editor['placements'][0])->toBeInstanceOf(ContentPlacement::class)->and($editor['placements'][0]->id)->toBe($placement->id)->and($editor['placements'][0]->block)->toBeInstanceOf(ContentBlock::class)->and($editor['placements'][0]->block?->definition->key)->toBe('hero')->and($editor['placements'][0]->block?->translations->firstWhere('locale', 'en')?->values['title'] ?? null)->toBe('Editor bootstrap')->and(json_encode($actionEditor, JSON_THROW_ON_ERROR))->toBe(json_encode($editor, JSON_THROW_ON_ERROR));
});
it('loads the editor bootstrap in a constant query count', function (): void {
    $actor = SystemActor::Background;
    $owner = TestContentOwner::query()->create(['name' => 'Editor query owner']);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'editor-query-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Editor query block']], 'metadata' => []], $actor);
    app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'block-1', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $measure = static function () use ($actor, $owner): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $editor = app(GetOwnerContentEditorAction::class)->execute($owner, 'homepage', $actor);
            $queryCount = count(DB::getQueryLog());
            $projected = array_map(ContentPlacementData::fromBatchProjection(...), $editor['placements']);
            foreach ($projected as $placement) {
                $placement->toArray();
            }
            expect(DB::getQueryLog())->toHaveCount($queryCount);
            $tables = [(new ContentPlacement)->getTable(), (new ContentBlock)->getTable(), (new ContentBlockTranslation)->getTable(), (new ContentDefinition)->getTable(), $owner->getTable()];
            $dataQueries = collect(DB::getQueryLog())->filter(static function (array $query) use ($tables): bool {
                foreach ($tables as $table) {
                    if (str_contains($query['query'], 'from "'.$table.'"') || str_contains($query['query'], 'join "'.$table.'"')) {
                        return true;
                    }
                }

                return false;
            })->count();
            expect($dataQueries)->toBe(5);

            return [$queryCount, count($editor['placements'])];
        } finally {
            DB::disableQueryLog();
        }
    };
    [$singleQueryCount] = $measure();
    foreach (range(2, 20) as $index) {
        app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => "block-{$index}", 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    }
    [$populatedQueryCount, $placementCount] = $measure();
    expect($placementCount)->toBe(20)->and($singleQueryCount)->toBeGreaterThanOrEqual(5)->and($populatedQueryCount)->toBe($singleQueryCount);
});
it('orders editor placements deterministically by region order and id', function (): void {
    $actor = SystemActor::Background;
    $owner = TestContentOwner::query()->create(['name' => 'Ordered editor owner']);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'ordered-editor-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Ordered editor block']], 'metadata' => []], $actor);
    $sidebar = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'sidebar', 'region' => 'sidebar', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $later = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'later', 'region' => 'main', 'parentId' => null, 'sortOrder' => 10, 'isVisible' => true, 'overrides' => []], $actor);
    $firstTie = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'first-tie', 'region' => 'main', 'parentId' => null, 'sortOrder' => 5, 'isVisible' => true, 'overrides' => []], $actor);
    $secondTie = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'second-tie', 'region' => 'main', 'parentId' => null, 'sortOrder' => 5, 'isVisible' => true, 'overrides' => []], $actor);
    $tieIds = [$firstTie->id, $secondTie->id];
    sort($tieIds);
    $editor = app(GetOwnerContentEditorAction::class)->execute($owner, 'homepage', $actor);
    expect(collect($editor['placements'])->pluck('id')->all())->toBe([...$tieIds, $later->id, $sidebar->id]);
});
it('bulk loads authorized owner placement summaries at a constant query count', function (): void {
    $system = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'bulk-summary-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Bulk summary block']], 'metadata' => []], $system);
    $owners = collect(range(1, 25))->map(static fn (int $index): TestContentOwner => TestContentOwner::query()->create(['name' => "Bulk owner {$index}"]));
    foreach ($owners as $index => $owner) {
        app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => "placement-{$index}", 'region' => $index % 2 === 0 ? 'main' : 'sidebar', 'parentId' => null, 'sortOrder' => $index, 'isVisible' => true, 'overrides' => []], $system);
    }
    $authorization = new class implements ContentAuthorization
    {
        /** @var list<string> */
        public array $ownerIds = [];

        /** @var list<bool> */
        public array $includesBlocks = [];

        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            if ($ability === ContentAbility::ListPlacements && $owner !== null) {
                $this->owner_ids[] = (string) $owner->getKey();
                $this->includesBlocks[] = ($context['includes_blocks'] ?? null) === true;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    $actor = new GenericUser(['id' => 'bulk-editor']);
    $measure = static function (array $owners) use ($actor): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $summaries = app(ContentReader::class)->summaries($owners, 'homepage', $actor);

            return [count(DB::getQueryLog()), $summaries];
        } finally {
            DB::disableQueryLog();
        }
    };
    [$singleQueryCount, $single] = $measure([$owners[0]]);
    $authorization->owner_ids = [];
    $authorization->includesBlocks = [];
    [$bulkQueryCount, $bulk] = $measure($owners->all());
    expect($single)->toHaveKey(TestContentOwner::class.':'.$owners[0]->id)->and($single[TestContentOwner::class.':'.$owners[0]->id][0])->toBeInstanceOf(ContentPlacement::class)->and($single[TestContentOwner::class.':'.$owners[0]->id][0]->block)->toBeInstanceOf(ContentBlock::class)->and($single[TestContentOwner::class.':'.$owners[0]->id][0]->block?->translations->firstWhere('locale', 'en')?->values['title'] ?? null)->toBe('Bulk summary block')->and($bulk)->toHaveCount(25)->and(array_keys($bulk))->toBe($owners->map(static fn (TestContentOwner $owner): string => TestContentOwner::class.':'.$owner->id)->all())->and($authorization->owner_ids)->toBe($owners->pluck('id')->all())->and($authorization->includesBlocks)->toBe(array_fill(0, 25, true))->and($bulkQueryCount)->toBe($singleQueryCount);
});
it('uses serialization-safe canonical keys for mixed string and integer owners', function (): void {
    app(ContentOwnerRegistry::class)->register('integer-page', TestIntegerContentOwner::class);
    app(ContentOwnerRegistry::class)->register('string-page', TestStringContentOwner::class);
    $stringOwner = TestStringContentOwner::query()->create(['id' => '1', 'name' => 'String one']);
    $integerOwner = TestIntegerContentOwner::query()->forceCreate(['id' => 1, 'name' => 'Integer one']);
    expect($integerOwner->getKey())->toBe(1);
    $summaries = app(ContentReader::class)->summaries([$stringOwner, $integerOwner], 'default', SystemActor::Background);
    expect(array_keys($summaries))->toBe([TestStringContentOwner::class.':1', TestIntegerContentOwner::class.':1'])->and(json_encode($summaries, JSON_THROW_ON_ERROR))->toStartWith('{');
});
it('bounds and short circuits bulk owner placement summaries', function (): void {
    $actor = SystemActor::Background;
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        expect(app(ContentReader::class)->summaries([], 'homepage', $actor))->toBe([])->and(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
    $owners = collect(range(1, 101))->map(static fn (int $index): TestContentOwner => TestContentOwner::query()->create(['name' => "Bounded owner {$index}"]));
    expect(fn () => app(ContentReader::class)->summaries($owners, 'homepage', $actor))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ContentReader::class)->summaries(array_fill(0, 101, $owners[0]), 'homepage', $actor))->toThrow(InvalidArgumentException::class);
});
it('authorizes every bulk summary owner before querying package or owner storage', function (): void {
    $owners = collect(range(1, 2))->map(static fn (int $index): TestContentOwner => TestContentOwner::query()->create(['name' => "Denied owner {$index}"]));
    $authorization = new class implements ContentAuthorization
    {
        public int $calls = 0;

        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            if ($ability !== ContentAbility::ListPlacements) {
                return;
            }
            $this->calls++;
            if ($this->calls === 2) {
                throw new AuthorizationException;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        expect(fn () => app(ContentReader::class)->summaries([$owners[0], $owners[0], $owners[1]], 'homepage', new GenericUser(['id' => 'denied-editor'])))->toThrow(AuthorizationException::class)->and($authorization->calls)->toBe(2)->and(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
});
it('makes placed block disclosure explicit without changing legacy placement context', function (): void {
    $owner = TestContentOwner::query()->create(['name' => 'Projection policy owner']);
    $authorization = new class implements ContentAuthorization
    {
        /** @var list<array<string, mixed>> */
        public array $contexts = [];

        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            if ($ability === ContentAbility::ListPlacements) {
                $this->contexts[] = $context;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    $action = app(ContentReader::class);
    $actor = new GenericUser(['id' => 'projection-policy']);
    $withoutBlocks = $action->placements($owner, 'homepage', $actor);
    $withBlocks = $action->placements($owner, 'homepage', $actor, includeBlocks: true);
    expect($authorization->contexts)->toBe([['group' => 'homepage'], ['group' => 'homepage', 'includes_blocks' => true]])->and($withoutBlocks)->each->toBeInstanceOf(ContentPlacement::class)->and($withBlocks)->each->toBeInstanceOf(ContentPlacement::class);
});
it('authorizes editor catalogs before reading placement or block storage', function (): void {
    $owner = TestContentOwner::query()->create(['name' => 'Denied editor owner']);
    $authorization = new class implements ContentAuthorization
    {
        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            if ($ability === ContentAbility::ListDefinitions) {
                throw new AuthorizationException;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        expect(fn () => app(GetOwnerContentEditorAction::class)->execute($owner, 'homepage', new GenericUser(['id' => 'denied-editor-catalog'])))->toThrow(AuthorizationException::class)->and(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
});
it('rejects stale owners and per-owner placement overflow in bulk summaries', function (): void {
    $actor = SystemActor::Background;
    $stale = TestContentOwner::query()->create(['name' => 'Stale owner']);
    TestContentOwner::query()->whereKey($stale->id)->delete();
    expect(fn () => app(ContentReader::class)->summaries([$stale], 'homepage', $actor))->toThrow(InvalidArgumentException::class);
    $owner = TestContentOwner::query()->create(['name' => 'Overflow owner']);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'overflow-summary-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Overflow summary block']], 'metadata' => []], $actor);
    foreach (range(1, 2) as $index) {
        app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => "overflow-{$index}", 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    }
    config()->set('nvl-content.placements.maximum_per_group', 1);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        expect(fn () => app(ContentReader::class)->summaries([$owner], 'homepage', $actor))->toThrow(InvalidArgumentException::class);
        $placementQuery = collect(DB::getQueryLog())->first(static fn (array $query): bool => str_contains($query['query'], 'content_placements'));
        expect($placementQuery['query'] ?? null)->toContain('limit 2');
    } finally {
        DB::disableQueryLog();
    }
});
it('finds exact block and owner-scoped placement DTOs without exposing models', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'editor-lookup-block', 'scope' => 'site', 'scopeKey' => 'lookup-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Lookup block']], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Lookup owner']);
    $placement = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'hero-slot', 'region' => 'main', 'parentId' => null, 'sortOrder' => 10, 'isVisible' => true, 'overrides' => []], $actor);
    $blockData = app(ContentReader::class)->find('editor-lookup-block', $actor);
    $byId = app(ContentReader::class)->placement($owner, 'homepage', $placement->id, $actor);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $byKey = app(ContentReader::class)->placement($owner, 'homepage', 'hero-slot', $actor);
        $placementQuery = collect(DB::getQueryLog())->first(static fn (array $query): bool => str_contains($query['query'], 'content_placements'));
        $wrappedPlacementId = DB::connection()->getQueryGrammar()->wrap('content_placements.id');
        expect($placementQuery['query'] ?? null)->toBeString()->not->toContain($wrappedPlacementId);
    } finally {
        DB::disableQueryLog();
    }
    expect($blockData)->toBeInstanceOf(ContentBlock::class)->and($blockData->key)->toBe('editor-lookup-block')->and($blockData->translations->firstWhere('locale', 'en')?->values['title'] ?? null)->toBe('Lookup block')->and($byId)->toBeInstanceOf(ContentPlacement::class)->and($byId)->toEqual($byKey)->and($byId->id)->toBe($placement->id)->and($byId->block)->toBeInstanceOf(ContentBlock::class)->and($byId->block?->key)->toBe('editor-lookup-block');
});
it('keeps editor block and placement key lookups byte exact', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'Editor-Case-Block', 'scope' => 'site', 'scopeKey' => 'case-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Case lookup owner']);
    app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'Hero-Case-Slot', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    expect(fn () => app(ContentReader::class)->find('editor-case-block', $actor))->toThrow(ModelNotFoundException::class)->and(fn () => app(ContentReader::class)->placement($owner, 'homepage', 'hero-case-slot', $actor))->toThrow(ModelNotFoundException::class);
});
it('compiles byte-exact value comparisons for supported database drivers', function (string $driver, string $identityFragment, string $lengthFragment): void {
    $grammar = DB::connection()->getQueryGrammar();
    $expression = (new ExactTextValueComparison('wrapped_key', $driver))->getValue($grammar);
    expect($expression)->toContain($identityFragment)->toContain($lengthFragment)->and(substr_count($expression, '?'))->toBe(2);
})->with(['MySQL' => ['mysql', 'BINARY wrapped_key = BINARY ?', 'OCTET_LENGTH'], 'MariaDB' => ['mariadb', 'BINARY wrapped_key = BINARY ?', 'OCTET_LENGTH'], 'PostgreSQL' => ['pgsql', 'COLLATE "C"', 'OCTET_LENGTH'], 'SQL Server' => ['sqlsrv', 'Latin1_General_100_BIN2', 'DATALENGTH'], 'SQLite' => ['sqlite', 'COLLATE BINARY', 'LENGTH(CAST']]);
it('rejects ambiguous unavailable and unauthorized editor identity reads', function (): void {
    $actor = SystemActor::Background;
    app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'ambiguous-block-key', 'scope' => 'site', 'scopeKey' => 'first-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'ambiguous-block-key', 'scope' => 'site', 'scopeKey' => 'second-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    expect(fn () => app(ContentReader::class)->find('ambiguous-block-key', $actor))->toThrow(InvalidArgumentException::class)->and(fn () => app(ContentReader::class)->find('missing-block-key', $actor))->toThrow(ModelNotFoundException::class);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'ambiguous-placement-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Ambiguous lookup owner']);
    $foreignOwner = TestContentOwner::query()->create(['name' => 'Foreign lookup owner']);
    $placement = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'first-placement', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => $placement->id, 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    expect(fn () => app(ContentReader::class)->placement($owner, 'homepage', $placement->id, $actor))->toThrow(InvalidArgumentException::class)->and(fn () => app(ContentReader::class)->placement($foreignOwner, 'homepage', $placement->id, $actor))->toThrow(ModelNotFoundException::class)->and(fn () => app(ContentReader::class)->placement($owner, 'secondary', 'first-placement', $actor))->toThrow(ModelNotFoundException::class);
    app()->instance(ContentAuthorization::class, new class implements ContentAuthorization
    {
        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            throw new AuthorizationException;
        }
    });
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        expect(fn () => app(ContentReader::class)->placement($owner, 'homepage', 'first-placement', new GenericUser(['id' => 'denied-lookup'])))->toThrow(AuthorizationException::class)->and(collect(DB::getQueryLog())->contains(static fn (array $query): bool => str_contains($query['query'], 'content_placements')))->toBeFalse()->and(fn () => app(ContentReader::class)->find('ambiguous-placement-block', new GenericUser(['id' => 'denied-block-lookup'])))->toThrow(AuthorizationException::class);
    } finally {
        DB::disableQueryLog();
    }
});
it('replaces one owner placement block without changing its tree facts', function (): void {
    Event::fake([ContentPlacementChanged::class]);
    $actor = SystemActor::Background;
    $original = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-original', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $replacement = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-next', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Replacement owner']);
    $placement = app(ContentPlacements::class)->place($original, $owner, 'homepage', ['key' => 'replace-slot', 'region' => 'sidebar', 'parentId' => null, 'sortOrder' => 20, 'isVisible' => false, 'overrides' => ['enabled' => false]], $actor);
    Event::fake([ContentPlacementChanged::class]);
    $result = app(ContentPlacements::class)->replace(owner: $owner, group: 'homepage', placement: $placement->id, block: $replacement->id, expectedRevision: $placement->revision, actor: $actor);
    $stored = ContentPlacement::query()->findOrFail($placement->id);
    expect($result)->toBeInstanceOf(ContentPlacement::class)->and($result->content_block_id)->toBe($replacement->id)->and($result->revision)->toBe($placement->revision + 1)->and($result->block?->key)->toBe('replace-next')->and($stored->key)->toBe('replace-slot')->and($stored->parent_id)->toBeNull()->and($stored->region)->toBe('sidebar')->and($stored->sort_order)->toBe(20)->and($stored->is_visible)->toBeFalse()->and($stored->overrides)->toBe(['enabled' => false]);
    Event::assertDispatched(ContentPlacementChanged::class, static fn (ContentPlacementChanged $event): bool => $event->placementId === $placement->id && $event->event === ContentPlacementEvent::Updated && $event->revision === 2 && $event->ownerType === TestContentOwner::class && $event->ownerId === $owner->id && $event->group === 'homepage' && $event->blockId === $replacement->id);
});
it('rejects stale foreign hidden and definition-invalid placement replacements', function (): void {
    $actor = SystemActor::Background;
    $original = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-guard-original', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $replacement = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-guard-next', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $hidden = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-guard-hidden', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $hidden->delete();
    $owner = TestContentOwner::query()->create(['name' => 'Replacement guard owner']);
    $foreignOwner = TestContentOwner::query()->create(['name' => 'Foreign replacement owner']);
    $placement = app(ContentPlacements::class)->place($original, $owner, 'homepage', ['key' => 'replace-guard-slot', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => ['enabled' => false]], $actor);
    $replace = app(ContentPlacements::class);
    expect(fn () => $replace->replace($owner, 'homepage', $placement->id, $replacement->id, 999, $actor))->toThrow(StaleContentException::class)->and(fn () => $replace->replace($foreignOwner, 'homepage', $placement->id, $replacement->id, $placement->revision, $actor))->toThrow(ModelNotFoundException::class)->and(fn () => $replace->replace($owner, 'secondary', $placement->id, $replacement->id, $placement->revision, $actor))->toThrow(ModelNotFoundException::class)->and(fn () => $replace->replace($owner, 'homepage', $placement->id, $hidden->id, $placement->revision, $actor))->toThrow(ModelNotFoundException::class);
    app(ContentDefinitionRegistry::class)->register(new ContentDefinitionSource(key: 'replacement-contract', name: 'Replacement contract', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title']]], allowedScopes: ['site'], allowedRegions: ['main', 'sidebar']));
    app(ContentDefinitions::class)->sync($actor);
    $incompatible = app(ContentBlocks::class)->create(['definition' => 'replacement-contract', 'key' => 'replace-incompatible', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    expect(fn () => $replace->replace($owner, 'homepage', $placement->id, $incompatible->id, $placement->revision, $actor))->toThrow(InvalidArgumentException::class);
    expect($placement->refresh()->content_block_id)->toBe($original->id)->and($placement->revision)->toBe(1);
});
it('serializes placement replacement and retries one deadlock without duplicating revision', function (): void {
    $actor = SystemActor::Background;
    $original = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-lock-original', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $replacement = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-lock-next', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Replacement lock owner']);
    $placement = app(ContentPlacements::class)->place($original, $owner, 'homepage', ['key' => 'replace-lock-slot', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    config()->set('nvl-content.placements.lock_wait_seconds', 1);
    $store = app(Repository::class)->getStore();
    expect($store)->toBeInstanceOf(LockProvider::class);
    if (! $store instanceof LockProvider) {
        return;
    }
    $key = 'nvl:content:placement-owner:'.hash('sha256', $owner->getMorphClass()."\x00{$owner->id}\x00homepage");
    $lock = $store->lock($key, 10);
    expect($lock->get())->toBeTrue();
    try {
        expect(fn () => app(ContentPlacements::class)->replace($owner, 'homepage', $placement->id, $replacement->id, $placement->revision, $actor))->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }
    $connection = DB::connection((new ContentPlacement)->getConnectionName());
    $connection->commit();
    $attempts = 0;
    ContentPlacement::updated(static function (ContentPlacement $updated) use (&$attempts, $placement): void {
        if ($updated->id !== $placement->id) {
            return;
        }
        $attempts++;
        if ($attempts === 1) {
            throw new RuntimeException('deadlock detected', 40001);
        }
    });
    $result = null;
    try {
        $result = app(ContentPlacements::class)->replace($owner, 'homepage', $placement->id, $replacement->id, $placement->revision, $actor);
    } finally {
        if (! $connection->getPdo()->inTransaction()) {
            $connection->beginTransaction();
        }
        RefreshDatabaseState::$migrated = false;
    }
    expect($result)->toBeInstanceOf(ContentPlacement::class);
    assert($result instanceof ContentPlacement);
    expect($attempts)->toBe(2)->and($result->revision)->toBe(2)->and($placement->refresh()->revision)->toBe(2)->and($placement->content_block_id)->toBe($replacement->id);
});
it('rolls back placement replacement when a post-write model hook fails', function (): void {
    $actor = SystemActor::Background;
    $original = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-rollback-original', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $replacement = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'replace-rollback-next', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Replacement rollback owner']);
    $placement = app(ContentPlacements::class)->place($original, $owner, 'homepage', ['key' => 'replace-rollback-slot', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    ContentPlacement::updated(static function (ContentPlacement $updated) use ($placement): void {
        if ($updated->id === $placement->id) {
            throw new RuntimeException('force replacement rollback');
        }
    });
    expect(fn () => app(ContentPlacements::class)->replace($owner, 'homepage', $placement->id, $replacement->id, $placement->revision, $actor))->toThrow(RuntimeException::class, 'force replacement rollback');
    expect($placement->refresh()->revision)->toBe(1)->and($placement->content_block_id)->toBe($original->id);
});
it('exposes explicit authorization contexts for replacement and reorder workflows', function (): void {
    $system = SystemActor::Background;
    $original = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'workflow-context-original', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $system);
    $replacement = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'workflow-context-next', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $system);
    $owner = TestContentOwner::query()->create(['name' => 'Workflow context owner']);
    $placement = app(ContentPlacements::class)->place($original, $owner, 'homepage', ['key' => 'workflow-context-slot', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $system);
    $authorization = new class implements ContentAuthorization
    {
        /** @var list<array<string, mixed>> */
        public array $placeContexts = [];

        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            if ($ability === ContentAbility::Place) {
                $this->placeContexts[] = $context;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    $actor = new GenericUser(['id' => 'workflow-context-editor']);
    $replaced = app(ContentPlacements::class)->replace($owner, 'homepage', $placement->id, $replacement->id, $placement->revision, $actor);
    app(ContentPlacements::class)->move($owner, 'homepage', ['placements' => [['id' => $replaced->id, 'expectedRevision' => $replaced->revision, 'region' => 'main', 'parentId' => null, 'sortOrder' => 10]]], $actor);
    expect($authorization->placeContexts)->toBe([['group' => 'homepage', 'region' => 'main', 'parent_id' => null, 'replaces_placement' => true], ['group' => 'homepage', 'region' => 'main', 'parent_id' => null, 'reorders_placements' => true]]);
});
it('validates and atomically applies a complete deterministic placement reorder', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'reorder-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Reorder owner']);
    $first = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'first', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $second = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'second', 'region' => 'main', 'parentId' => null, 'sortOrder' => 10, 'isVisible' => true, 'overrides' => []], $actor);
    $child = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'child', 'region' => 'main', 'parentId' => $first->id, 'sortOrder' => 5, 'isVisible' => true, 'overrides' => []], $actor);
    $data = ['placements' => [['id' => $child->id, 'expectedRevision' => $child->revision, 'region' => 'sidebar', 'parentId' => $first->id, 'sortOrder' => 0], ['id' => $second->id, 'expectedRevision' => $second->revision, 'region' => 'main', 'parentId' => null, 'sortOrder' => 10], ['id' => $first->id, 'expectedRevision' => $first->revision, 'region' => 'sidebar', 'parentId' => null, 'sortOrder' => 5]]];
    expect($data['placements'][0])->toBeArray();
    Event::fake([ContentPlacementChanged::class]);
    $orderedPlacements = app(ContentPlacements::class)->move($owner, 'homepage', $data, $actor);
    expect(collect($orderedPlacements)->pluck('id')->all())->toBe([$second->id, $child->id, $first->id])->and($first->refresh()->revision)->toBe(2)->and($first->region)->toBe('sidebar')->and($second->refresh()->revision)->toBe(1)->and($child->refresh()->revision)->toBe(2)->and($child->region)->toBe('sidebar');
    $events = Event::dispatched(ContentPlacementChanged::class)->map(static fn (array $dispatch): ContentPlacementChanged => $dispatch[0]);
    $eventIds = $events->pluck('placementId')->all();
    $expectedEventIds = [$first->id, $child->id];
    sort($expectedEventIds);
    expect($eventIds)->toBe($expectedEventIds);
    foreach ($events as $event) {
        expect($event->event)->toBe(ContentPlacementEvent::Updated)->and($event->revision)->toBe(2)->and($event->ownerType)->toBe(TestContentOwner::class)->and($event->ownerId)->toBe($owner->id)->and($event->group)->toBe('homepage')->and($event->blockId)->toBe($block->id);
    }
});
it('rejects partial duplicate stale cyclic cross-region and oversized reorder proposals', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'reorder-guards-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Reorder guards owner']);
    $first = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'first', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $second = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'second', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $reorder = app(ContentPlacements::class);
    $item = static fn (ContentPlacement $placement, ?string $parentId = null, string $region = 'main', ?int $revision = null, int $sortOrder = 0): array => ['id' => $placement->id, 'expectedRevision' => $revision ?? $placement->revision, 'region' => $region, 'parentId' => $parentId, 'sortOrder' => $sortOrder];
    expect(fn () => $reorder->move($owner, 'homepage', ['placements' => [$item($first)]], $actor))->toThrow(InvalidArgumentException::class)->and(fn () => $reorder->move($owner, 'homepage', ['placements' => [$item($first), $item($first)]], $actor))->toThrow(InvalidArgumentException::class)->and(fn () => $reorder->move($owner, 'homepage', ['placements' => [$item($first, revision: 999), $item($second)]], $actor))->toThrow(StaleContentException::class)->and(fn () => $reorder->move($owner, 'homepage', ['placements' => [$item($first, $second->id), $item($second, $first->id)]], $actor))->toThrow(InvalidArgumentException::class)->and(fn () => $reorder->move($owner, 'homepage', ['placements' => [$item($first), $item($second, $first->id, 'sidebar')]], $actor))->toThrow(InvalidArgumentException::class);
    config()->set('nvl-content.placements.maximum_per_group', 1);
    expect(fn () => $reorder->move($owner, 'homepage', ['placements' => [$item($first), $item($second)]], $actor))->toThrow(ValidationException::class);
    expect($first->refresh()->revision)->toBe(1)->and($first->parent_id)->toBeNull()->and($second->refresh()->revision)->toBe(1)->and($second->parent_id)->toBeNull();
});
it('revalidates each proposed reorder region against its block definition', function (): void {
    $actor = SystemActor::Background;
    app(ContentDefinitionRegistry::class)->register(new ContentDefinitionSource(key: 'main-only-reorder', name: 'Main-only reorder', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => []], allowedScopes: ['site'], allowedRegions: ['main']));
    app(ContentDefinitions::class)->sync($actor);
    $block = app(ContentBlocks::class)->create(['definition' => 'main-only-reorder', 'key' => 'main-only-reorder-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Main-only reorder owner']);
    $placement = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'main-only', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    expect(fn () => app(ContentPlacements::class)->move($owner, 'homepage', ['placements' => [['id' => $placement->id, 'expectedRevision' => $placement->revision, 'region' => 'sidebar', 'parentId' => null, 'sortOrder' => 0]]], $actor))->toThrow(InvalidArgumentException::class);
    expect($placement->refresh()->region)->toBe('main')->and($placement->revision)->toBe(1);
});
it('authorizes a complete reorder before writing and rolls back any partial batch', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'reorder-atomic-block', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Reorder atomic owner']);
    $first = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'first', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $second = app(ContentPlacements::class)->place($block, $owner, 'homepage', ['key' => 'second', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $proposal = ['placements' => [['id' => $first->id, 'expectedRevision' => 1, 'region' => 'main', 'parentId' => null, 'sortOrder' => 10], ['id' => $second->id, 'expectedRevision' => 1, 'region' => 'main', 'parentId' => null, 'sortOrder' => 20]]];
    $originalAuthorization = app(ContentAuthorization::class);
    $authorization = new class implements ContentAuthorization
    {
        public int $calls = 0;

        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            if ($ability !== ContentAbility::Place) {
                return;
            }
            $this->calls++;
            if ($this->calls === 2) {
                throw new AuthorizationException;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    expect(fn () => app(ContentPlacements::class)->move($owner, 'homepage', $proposal, new GenericUser(['id' => 'denied-reorder'])))->toThrow(AuthorizationException::class)->and($authorization->calls)->toBe(2)->and($first->refresh()->revision)->toBe(1)->and($first->sort_order)->toBe(0)->and($second->refresh()->revision)->toBe(1)->and($second->sort_order)->toBe(0);
    app()->instance(ContentAuthorization::class, $originalAuthorization);
    $orderedIds = [$first->id, $second->id];
    sort($orderedIds);
    $failOn = $orderedIds[1];
    ContentPlacement::updated(static function (ContentPlacement $updated) use ($failOn): void {
        if ($updated->id === $failOn) {
            throw new RuntimeException('force reorder rollback');
        }
    });
    Event::fake([ContentPlacementChanged::class]);
    expect(fn () => app(ContentPlacements::class)->move($owner, 'homepage', $proposal, $actor))->toThrow(RuntimeException::class, 'force reorder rollback');
    expect($first->refresh()->revision)->toBe(1)->and($first->sort_order)->toBe(0)->and($second->refresh()->revision)->toBe(1)->and($second->sort_order)->toBe(0);
    Event::assertNotDispatched(ContentPlacementChanged::class);
});
it('reports persisted placements outside their owner group declaration', function (): void {
    $actor = SystemActor::Background;
    $owner = TestContentOwner::query()->create(['name' => 'Group drift']);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'group-drift', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Group drift']], 'metadata' => []], $actor);
    $placement = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'hero', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    ContentPlacement::query()->whereKey($placement->id)->update(['group' => 'undeclared']);
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"placements.declared_groups": false');
});
it('stores only explicitly overridden nested placement leaves', function (): void {
    app(ContentDefinitionRegistry::class)->register(new ContentDefinitionSource(key: 'nested-overrides', name: 'Nested overrides', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'settings', 'type' => 'object', 'label' => 'Settings', 'fields' => [['key' => 'theme', 'type' => 'text', 'label' => 'Theme'], ['key' => 'gap', 'type' => 'integer', 'label' => 'Gap']]]]], allowedScopes: ['site']));
    app(ContentDefinitions::class)->sync(SystemActor::Background);
    $block = app(ContentBlocks::class)->create(['definition' => 'nested-overrides', 'key' => 'nested-overrides', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => ['settings' => ['theme' => 'light', 'gap' => 4]], 'translations' => [], 'metadata' => []], SystemActor::Background);
    $owner = TestContentOwner::query()->create(['name' => 'Overrides page']);
    $placement = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'nested', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => ['settings' => ['gap' => 8]]], SystemActor::Background);
    expect($placement->overrides)->toBe(['settings' => ['gap' => 8]]);
});
it('compiles definitions strictly before registration', function (): void {
    $registry = app(ContentDefinitionRegistry::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'unknown-field-property', name: 'Unknown field property', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title', 'requird' => true]]])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'coerced-boolean', name: 'Coerced boolean', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => 'false']]])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'irrelevant-children', name: 'Irrelevant children', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title', 'fields' => [['key' => 'child', 'type' => 'text', 'label' => 'Child']]]]])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'invalid-json-schema', name: 'Invalid JSON Schema', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'payload', 'type' => 'json', 'label' => 'Payload', 'settings' => ['schema' => ['type' => 123]]]]])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'missing-reference-resolver', name: 'Missing reference resolver', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'resource', 'type' => 'reference', 'label' => 'Resource', 'settings' => ['reference_type' => 'missing']]]])))->toThrow(InvalidArgumentException::class);
    config()->set(['nvl-content.definitions' => ['unknown-definition-property' => ['name' => 'Unknown definition property', 'schema' => ['fields' => []], 'versoin' => 2]], 'nvl-content.definition_paths' => []]);
    expect(fn () => app(ContentDefinitionLoader::class)->load())->toThrow(InvalidArgumentException::class);
});
it('validates definition defaults without resolving external resources at boot', function (): void {
    $registry = app(ContentDefinitionRegistry::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'invalid-defaults', name: 'Invalid defaults', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled']]], defaults: ['enabled' => 'yes'], allowedScopes: ['site'])))->toThrow(InvalidArgumentException::class);
    $registry->register(new ContentDefinitionSource(key: 'external-default-shape', name: 'External default shape', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'image', 'type' => 'media', 'label' => 'Image']]], defaults: ['image' => '550e8400-e29b-41d4-a716-446655440000'], allowedScopes: ['site']));
    expect($registry->get('external-default-shape')['defaults'])->toBe(['image' => '550e8400-e29b-41d4-a716-446655440000']);
});
it('validates recursive schema field defaults without requiring payload values', function (): void {
    $registry = app(ContentDefinitionRegistry::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'invalid-localized-field-default', name: 'Invalid localized field default', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'title', 'type' => 'boolean', 'label' => 'Title', 'localized' => true, 'default' => 'yes']]], allowedScopes: ['site'])))->toThrow(InvalidArgumentException::class);
});
it('validates nested field defaults when their parent has no payload default', function (): void {
    $registry = app(ContentDefinitionRegistry::class);
    expect(fn () => $registry->register(new ContentDefinitionSource(key: 'invalid-nested-field-default', name: 'Invalid nested field default', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'settings', 'type' => 'object', 'label' => 'Settings', 'fields' => [['key' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled', 'default' => 'yes']]]]], allowedScopes: ['site'])))->toThrow(InvalidArgumentException::class);
});
it('keeps valid external schema field defaults shape-only at boot', function (): void {
    $registry = app(ContentDefinitionRegistry::class);
    $registry->register(new ContentDefinitionSource(key: 'valid-recursive-field-defaults', name: 'Valid recursive field defaults', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled', 'localized' => true, 'default' => true], ['key' => 'image', 'type' => 'media', 'label' => 'Image', 'localized' => true, 'default' => '550e8400-e29b-41d4-a716-446655440000'], ['key' => 'article', 'type' => 'reference', 'label' => 'Article', 'localized' => true, 'default' => 'missing-article', 'settings' => ['reference_type' => 'article']], ['key' => 'settings', 'type' => 'object', 'label' => 'Settings', 'fields' => [['key' => 'visible', 'type' => 'boolean', 'label' => 'Visible', 'default' => false]]]]], allowedScopes: ['site']));
    $fields = $registry->get('valid-recursive-field-defaults')['schema']->fields;
    expect($fields[0]->default)->toBeTrue()->and($fields[1]->default)->toBe('550e8400-e29b-41d4-a716-446655440000')->and($fields[2]->default)->toBe('missing-article')->and($fields[3]->fields[0]->default)->toBeFalse();
});
it('rejects ambiguous and unsupported translation locale keys', function (): void {
    config()->set(['nvl-content.locales.available' => ['en-US'], 'nvl-translatable.locales' => ['en-US']]);
    expect(fn () => app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'duplicate-locales', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en_US' => ['title' => 'First'], 'en-US' => ['title' => 'Second']], 'metadata' => []], SystemActor::Background))->toThrow(ValidationException::class);
    config()->set(['nvl-content.locales.available' => [], 'nvl-translatable.locales' => ['en', 'bg']]);
    expect(fn () => app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'unsupported-locale', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['fr' => ['title' => 'Bonjour']], 'metadata' => []], SystemActor::Background))->toThrow(ValidationException::class);
});
it('returns stable unprocessable API errors for semantic content failures', function (): void {
    config()->set(['nvl-content.routes.management.enabled' => true, 'nvl-content.routes.management.prefix' => 'api/content-contract', 'nvl-content.routes.management.name' => 'content.contract.', 'nvl-content.routes.management.middleware' => []]);
    require __DIR__.'/../Fixtures/Consumer/routes/api.php';
    app('router')->getRoutes()->refreshNameLookups();
    $created = $this->postJson('/api/content-contract/blocks', ['definition' => 'hero', 'key' => 'object-fidelity', 'scope' => 'site', 'scopeKey' => 'main-site'])->assertCreated();
    expect($created->getContent())->toContain('"values":{"enabled":true}')->toContain('"translations":{}')->toContain('"metadata":{}');
    $this->postJson('/api/content-contract/blocks', ['definition' => 'hero', 'key' => 'invalid-json-schema-value', 'scope' => 'site', 'scopeKey' => 'main-site', 'values' => ['layout' => ['columns' => 0]]])->assertUnprocessable()->assertJsonPath('error.code', 'invalid_content')->assertJsonStructure(['message', 'error' => ['code', 'context']]);
});
it('bounds arbitrary JSON structures recursively', function (): void {
    app(ContentDefinitionRegistry::class)->register(new ContentDefinitionSource(key: 'bounded-json', name: 'Bounded JSON', description: null, category: 'testing', version: 1, view: null, schema: ['fields' => [['key' => 'payload', 'type' => 'json', 'label' => 'Payload', 'settings' => ['schema' => ['$id' => 'https://schemas.example.test/bounded-json', 'type' => 'object']]]]], allowedScopes: ['site']));
    app(ContentDefinitions::class)->sync(SystemActor::Background);
    config()->set('nvl-content.validation.maximum_depth', 3);
    expect(fn () => app(ContentBlocks::class)->create(['definition' => 'bounded-json', 'key' => 'deep-json', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => ['payload' => ['one' => ['two' => ['three' => true]]]], 'translations' => [], 'metadata' => []], SystemActor::Background))->toThrow(InvalidArgumentException::class);
});
it('validates exact scalar formats and option values', function (): void {
    $context = new ContentValidationContext(SystemActor::Background, 'en', 'field', ContentVisibility::Private);
    expect(fn () => (new StringFieldTypeAdapter('color'))->normalize('#12345', new ContentFieldDefinition('color', 'color', 'Color'), $context))->toThrow(InvalidArgumentException::class);
    expect(fn () => (new StringFieldTypeAdapter('date_time'))->normalize('2026-02-31T10:00:00+00:00', new ContentFieldDefinition('starts_at', 'date_time', 'Starts at'), $context))->toThrow(InvalidArgumentException::class);
    expect(fn () => (new StringFieldTypeAdapter('select'))->normalize('Published', new ContentFieldDefinition('status', 'select', 'Status', settings: ['options' => ['published' => 'Published']]), $context))->toThrow(InvalidArgumentException::class);
    expect(fn () => (new StringFieldTypeAdapter('uri'))->normalize('/safe\..\admin', new ContentFieldDefinition('href', 'uri', 'Link'), $context))->toThrow(InvalidArgumentException::class);
});
it('hard-denies unsafe semantic destinations despite consumer scheme allowlists', function (string $scheme, string $destination): void {
    config()->set('nvl-content.links.allowed_schemes', ['https', $scheme]);
    $context = new ContentValidationContext(SystemActor::Background, 'en', 'link.href', ContentVisibility::Private);
    expect(fn () => (new StringFieldTypeAdapter('uri'))->normalize($destination, new ContentFieldDefinition('href', 'uri', 'Destination'), $context))->toThrow(InvalidArgumentException::class);
})->with(['javascript' => ['javascript', 'javascript:alert(1)'], 'data' => ['data', 'data:text/html,unsafe'], 'vbscript' => ['vbscript', 'vbscript:msgbox(1)'], 'file' => ['file', 'file:///etc/passwd']]);
it('removes executable rich-text links despite runtime scheme configuration', function (): void {
    config()->set('nvl-content.rich_text.allowed_link_schemes', ['https', 'javascript']);
    $adapter = new RichTextFieldTypeAdapter;
    $context = new ContentValidationContext(SystemActor::Background, 'en', 'body', ContentVisibility::Private);
    $sanitized = $adapter->normalize('<p><a href="javascript:alert(1)">Unsafe</a></p>', new ContentFieldDefinition('body', 'rich_text', 'Body'), $context);
    expect($sanitized)->toBeString()->not->toContain('javascript:')->not->toContain('href=');
});
it('rejects unsafe global scheme configuration during package bootstrap', function (string $key): void {
    config()->set($key, ['https', 'javascript']);
    expect(fn () => (new ContentServiceProvider(app()))->register())->toThrow(InvalidArgumentException::class);
})->with(['semantic links' => 'nvl-content.links.allowed_schemes', 'URL fields' => 'nvl-content.validation.url_schemes', 'rich text links' => 'nvl-content.rich_text.allowed_link_schemes']);
it('rejects duplicate repeater keys', function (): void {
    expect(fn () => app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'duplicate-repeater-keys', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => ['links' => [['_key' => 'same', 'label' => 'One'], ['_key' => 'same', 'label' => 'Two']]], 'translations' => [], 'metadata' => []], SystemActor::Background))->toThrow(InvalidArgumentException::class);
});
it('requires localized repeater rows to reference stable existing base keys', function (): void {
    $schema = ContentSchema::fromArray([['key' => 'items', 'type' => 'repeater', 'label' => 'Items', 'fields' => [['key' => 'slug', 'type' => 'text', 'label' => 'Slug'], ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'localized' => true]]]]);
    $values = ['items' => [['_key' => 'first', 'slug' => 'first'], ['_key' => 'second', 'slug' => 'second']]];
    $validator = app(ContentValueValidator::class);
    expect(fn () => $validator->validate(schema: $schema, values: $values, translations: ['bg' => ['items' => [['label' => 'Първи']]]], actor: SystemActor::Background, visibility: ContentVisibility::Private))->toThrow(InvalidArgumentException::class);
    expect(fn () => $validator->validate(schema: $schema, values: $values, translations: ['bg' => ['items' => [['_key' => 'unknown', 'label' => 'Непознат']]]], actor: SystemActor::Background, visibility: ContentVisibility::Private))->toThrow(InvalidArgumentException::class);
});
it('matches reordered partial repeater translations by stable row key', function (): void {
    $schema = ContentSchema::fromArray([['key' => 'items', 'type' => 'repeater', 'label' => 'Items', 'fields' => [['key' => 'slug', 'type' => 'text', 'label' => 'Slug'], ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'localized' => true]]]]);
    $validated = app(ContentValueValidator::class)->validate(schema: $schema, values: ['items' => [['_key' => 'first', 'slug' => 'first'], ['_key' => 'second', 'slug' => 'second'], ['_key' => 'third', 'slug' => 'third']]], translations: ['bg' => ['items' => [['_key' => 'third', 'label' => 'Трети'], ['_key' => 'first', 'label' => 'Първи']]]], actor: SystemActor::Background, visibility: ContentVisibility::Private);
    $localized = $validated->translations['bg'];
    $overlaid = app(ContentLocalizedValues::class)->overlay($schema, $validated->values, $localized);
    expect(array_column($localized['items'], '_key'))->toBe(['third', 'first'])->and($overlaid['items'])->toBe([['_key' => 'first', 'slug' => 'first', 'label' => 'Първи'], ['_key' => 'second', 'slug' => 'second'], ['_key' => 'third', 'slug' => 'third', 'label' => 'Трети']]);
});
it('accepts empty PHP arrays for objects containing only localized descendants', function (): void {
    $schema = ContentSchema::fromArray([['key' => 'copy', 'type' => 'object', 'label' => 'Copy', 'fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title', 'localized' => true]]]]);
    $validated = app(ContentValueValidator::class)->validate(schema: $schema, values: ['copy' => []], translations: ['bg' => ['copy' => []]], actor: SystemActor::Background, visibility: ContentVisibility::Private);
    expect($validated->values)->toBe(['copy' => []])->and($validated->translations)->toBe(['bg' => ['copy' => []]]);
});
it('rechecks payload bounds after defaults expand normalized values', function (): void {
    config()->set('nvl-content.validation.maximum_payload_bytes', 64);
    $schema = ContentSchema::fromArray([['key' => 'message', 'type' => 'text', 'label' => 'Message', 'default' => str_repeat('x', 80)]]);
    expect(fn () => app(ContentValueValidator::class)->validate(schema: $schema, values: [], translations: [], actor: SystemActor::Background, visibility: ContentVisibility::Private))->toThrow(InvalidArgumentException::class);
});
it('reports unsynchronized orphan definitions in doctor diagnostics', function (): void {
    ContentDefinition::query()->create(['key' => 'removed-definition', 'name' => 'Removed definition', 'category' => 'testing', 'version' => 1, 'schema' => ['fields' => []], 'allowed_scopes' => ['site'], 'allowed_regions' => ['main'], 'is_active' => true, 'source_hash' => str_repeat('a', 64), 'synced_at' => now()]);
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"definitions.synchronized": false');
    app(ContentDefinitions::class)->sync(SystemActor::Background);
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertSuccessful()->expectsOutputToContain('"definitions.synchronized": true');
});
it('exposes the registered reference aliases without resolving them', function (): void {
    $registry = new ContentReferenceRegistry(app(), app(ContentPayloadGuard::class), app(OwnershipBoundary::class));
    expect($registry->has('missing'))->toBeFalse();
});
it('requires definition version progression for contract changes', function (): void {
    $definition = ContentDefinition::query()->where('key', 'hero')->firstOrFail();
    $definition->forceFill(['schema' => ['fields' => []], 'source_hash' => str_repeat('b', 64)])->save();
    expect(fn () => app(ContentDefinitions::class)->sync(SystemActor::Background))->toThrow(InvalidArgumentException::class);
});
it('allows same-version definition mirror repair when the contract is unchanged', function (): void {
    $definition = ContentDefinition::query()->where('key', 'hero')->firstOrFail();
    $definition->forceFill(['source_hash' => str_repeat('c', 64)])->save();
    app(ContentDefinitions::class)->sync(SystemActor::Background);
    expect($definition->refresh()->source_hash)->not->toBe(str_repeat('c', 64))->and($definition->version)->toBe(2);
});
it('rechecks the synchronized definition under lock before creating a block', function (): void {
    $mutated = false;
    DB::listen(function (QueryExecuted $query) use (&$mutated): void {
        if ($mutated || ! str_contains(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'content_definitions')) {
            return;
        }
        $mutated = true;
        ContentDefinition::query()->where('key', 'hero')->update(['source_hash' => str_repeat('0', 64)]);
    });
    expect(fn () => app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'definition-lock', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Definition lock']], 'metadata' => []], SystemActor::Background))->toThrow(InvalidArgumentException::class);
    expect(ContentBlock::query()->where('key', 'definition-lock')->exists())->toBeFalse();
});
it('replans definition synchronization after acquiring database locks', function (): void {
    $mutated = false;
    DB::listen(function (QueryExecuted $query) use (&$mutated): void {
        if ($mutated || ! str_contains(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'content_definitions')) {
            return;
        }
        $mutated = true;
        ContentDefinition::query()->where('key', 'hero')->update(['version' => 3, 'source_hash' => str_repeat('f', 64)]);
    });
    expect(fn () => app(ContentDefinitions::class)->sync(SystemActor::Background))->toThrow(InvalidArgumentException::class);
    expect(ContentDefinition::query()->where('key', 'hero')->value('version'))->toBe(3);
});
it('serializes definition synchronization across deployment processes', function (): void {
    config()->set('nvl-content.definition_sync.lock_wait_seconds', 1);
    $store = app(Repository::class)->getStore();
    expect($store)->toBeInstanceOf(LockProvider::class);
    if (! $store instanceof LockProvider) {
        return;
    }
    $lock = $store->lock('nvl:content:definitions:sync', 10);
    expect($lock->get())->toBeTrue();
    try {
        expect(fn () => app(ContentDefinitions::class)->sync(SystemActor::Background))->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }
});
it('applies one placement depth definition consistently', function (): void {
    config()->set('nvl-content.placements.maximum_depth', 2);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'depth', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], SystemActor::Background);
    $owner = TestContentOwner::query()->create(['name' => 'Depth page']);
    $root = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'root', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], SystemActor::Background);
    $child = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'child', 'region' => 'main', 'parentId' => $root->id, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], SystemActor::Background);
    expect($child->parent_id)->toBe($root->id);
    expect(fn () => app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'grandchild', 'region' => 'main', 'parentId' => $child->id, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], SystemActor::Background))->toThrow(InvalidArgumentException::class);
});
it('rejects reparenting a subtree beyond the placement depth limit', function (): void {
    config()->set('nvl-content.placements.maximum_depth', 3);
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'subtree-depth', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Subtree depth page']);
    $subtreeRoot = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'subtree-root', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'subtree-child', 'region' => 'main', 'parentId' => $subtreeRoot->id, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $targetRoot = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'target-root', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    $targetChild = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'target-child', 'region' => 'main', 'parentId' => $targetRoot->id, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    expect(fn () => app(ContentPlacements::class)->update($subtreeRoot, ['expectedRevision' => $subtreeRoot->revision, 'region' => 'main', 'parentId' => $targetChild->id, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor))->toThrow(InvalidArgumentException::class);
    expect($subtreeRoot->refresh()->parent_id)->toBeNull();
});
it('isolates reference display caches by the complete resolver context', function (): void {
    $adapter = new ReferenceFieldTypeAdapter(multiple: false, references: app(ContentReferenceRegistry::class));
    $field = new ContentFieldDefinition(key: 'article', type: 'reference', label: 'Article', settings: ['reference_type' => 'article']);
    $actor = SystemActor::Background;
    $resources = new ContentRenderResources;
    $primary = $adapter->render('article-1', $field, new ContentValidationContext(actor: $actor, locale: 'en', path: 'primary.article', visibility: ContentVisibility::Public, resources: $resources, publicOnly: true, group: 'default'));
    $secondary = $adapter->render('article-1', $field, new ContentValidationContext(actor: $actor, locale: 'en', path: 'secondary.article', visibility: ContentVisibility::Public, resources: $resources, publicOnly: true, group: 'default'));
    $privatePreview = $adapter->render('article-1', $field, new ContentValidationContext(actor: $actor, locale: 'en', path: 'primary.article', visibility: ContentVisibility::Private, resources: $resources, publicOnly: false, group: 'default'));
    expect($primary)->toBeArray()->and($secondary)->toBeArray()->and($privatePreview)->toBeArray()->and($primary['path'])->toBe('primary.article')->and($secondary['path'])->toBe('secondary.article')->and($privatePreview['visibility'])->toBe(ContentVisibility::Private->value)->and($privatePreview['public_only'])->toBeFalse();
});
it('replaces configured lists while recursively filling missing map defaults', function (): void {
    config()->set('nvl-content', ['links' => ['allowed_schemes' => ['https']], 'routes' => ['public' => ['middleware' => []]]]);
    (new ContentServiceProvider(app()))->register();
    expect(config('nvl-content.links.allowed_schemes'))->toBe(['https'])->and(config('nvl-content.links.allow_relative'))->toBeTrue()->and(config('nvl-content.routes.public.middleware'))->toBe([])->and(config('nvl-content.routes.public.enabled'))->toBeNull();
});
it('fails strict diagnostics for a default view rejected by live rendering', function (): void {
    config()->set('nvl-content.rendering.default_view', '');
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"view.default": false');
});
it('validates definition synchronization lock configuration', function (string $key): void {
    config()->set("nvl-content.definition_sync.{$key}", 0);
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"cache.definition_sync_locks": true');
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"locks.configuration": false');
})->with(['lease duration' => 'lock_seconds', 'wait duration' => 'lock_wait_seconds']);
it('rejects required database columns with incompatible semantics', function (string $semantic): void {
    if ($semantic === 'type' && DB::getDriverName() === 'pgsql') {
        $grammar = DB::getQueryGrammar();
        $table = $grammar->wrapTable(ContentConfiguration::table('blocks'));
        $column = $grammar->wrap('key');
        DB::statement("alter table {$table} alter column {$column} type integer using 0");
    } else {
        Schema::table(ContentConfiguration::table('blocks'), function (Blueprint $table) use ($semantic): void {
            if ($semantic === 'type') {
                $table->integer('key')->change();

                return;
            }
            if ($semantic === 'nullability') {
                $table->string('key', 191)->nullable()->change();

                return;
            }
            $table->string('status', 30)->default('published')->change();
        });
    }
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"schema.columns": false');
})->with(['incompatible type' => 'type', 'incompatible nullability' => 'nullability', 'incompatible default' => 'default']);
it('rejects same-named database indexes with incompatible semantics', function (): void {
    $table = ContentConfiguration::table('blocks');
    Schema::table($table, function (Blueprint $table): void {
        $table->dropIndex('content_blocks_scope_state_idx');
    });
    Schema::table($table, function (Blueprint $table): void {
        $table->index(['key'], 'content_blocks_scope_state_idx');
    });
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"schema.indexes": false');
});
it('rejects schemas missing required foreign key semantics', function (): void {
    Schema::table(ContentConfiguration::table('blocks_i18n'), function (Blueprint $table): void {
        $table->dropForeign(['content_block_id']);
    });
    $this->artisan('nvl:content:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed()->expectsOutputToContain('"schema.foreign_keys": false');
});
it('rejects conflicting keyed definition identities and normalized property aliases', function (): void {
    config()->set('nvl-content.definition_paths', []);
    config()->set('nvl-content.definitions', ['outer-key' => ['key' => 'inner-key', 'name' => 'Conflicting identity', 'schema' => ['fields' => []]]]);
    expect(fn () => app(ContentDefinitionLoader::class)->load())->toThrow(InvalidArgumentException::class);
    config()->set('nvl-content.definitions', [['key' => 'conflicting-aliases', 'name' => 'Conflicting aliases', 'allowed_scopes' => ['global'], 'allowedScopes' => ['site'], 'schema' => ['fields' => []]]]);
    expect(fn () => app(ContentDefinitionLoader::class)->load())->toThrow(InvalidArgumentException::class);
});
it('canonicalizes DTO block reads and does not query unused placements', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'canonical-model-read', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Canonical model read']], 'metadata' => []], $actor);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $read = app(ContentReader::class)->get($block, $actor);
        $queryCount = count(DB::getQueryLog());
        $read->toArray();
        $queries = collect(DB::getQueryLog());
        expect($read)->toBeInstanceOf(ContentBlock::class)->and($read->id)->toBe($block->id)->and($read->translations->firstWhere('locale', 'en')?->values['title'] ?? null)->toBe('Canonical model read')->and($queries)->toHaveCount($queryCount)->and($queries->contains(static fn (array $query): bool => str_contains($query['query'], 'content_placements')))->toBeFalse();
    } finally {
        DB::disableQueryLog();
    }
    ContentBlock::query()->whereKey($block->id)->delete();
    $deleted = ContentBlock::withTrashed()->findOrFail($block->id);
    expect(fn () => app(ContentReader::class)->get($deleted, $actor))->toThrow(ModelNotFoundException::class);
});
it('applies trusted actor scope before caller-controlled block filters', function (): void {
    $system = SystemActor::Background;
    foreach (['tenant-a', 'tenant-b'] as $tenant) {
        app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => "catalog-{$tenant}", 'scope' => 'site', 'scopeKey' => $tenant, 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => "Catalog {$tenant}"]], 'metadata' => ['tenant' => $tenant]], $system);
    }
    $authorization = new class implements ContentAuthorization, ContentBlockQueryScope
    {
        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void {}

        /**
         * @param  Builder<ContentBlock>  $query
         */
        public function scopeContentBlocks(Builder $query, Authenticatable|SystemActor|PublicActor $actor): void
        {
            $query->where('scope_key', $actor->getAuthIdentifier());
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    $actor = new GenericUser(['id' => 'tenant-a']);
    $page = app(ContentReader::class)->paginate([], $actor);
    $forged = app(ContentReader::class)->paginate(['scope_key' => 'tenant-b'], $actor);
    expect($page->total())->toBe(1)->and($page->items())->each->toBeInstanceOf(ContentBlock::class)->and($page->items()[0]->scope_key)->toBe('tenant-a')->and($forged->total())->toBe(0);
});
it('provides complete proposed create state to authorization', function (): void {
    $authorization = new class implements ContentAuthorization
    {
        /** @var list<array<string, mixed>> */
        public array $createContexts = [];

        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            if ($ability !== ContentAbility::Create) {
                return;
            }
            $this->createContexts[] = $context;
            if (($context['scope_key'] ?? null) !== 'tenant-a' || ($context['visibility'] ?? null) !== ContentVisibility::Private->value) {
                throw new AuthorizationException;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    $actor = new GenericUser(['id' => 'tenant-a']);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'authorized-create', 'scope' => 'site', 'scopeKey' => 'tenant-a', 'visibility' => ContentVisibility::Private, 'values' => [], 'translations' => ['en' => ['title' => 'Authorized create']], 'metadata' => []], $actor);
    expect($block->scope_key)->toBe('tenant-a')->and($authorization->createContexts[0])->toMatchArray(['definition' => 'hero', 'key' => 'authorized-create', 'scope' => 'site', 'scope_key' => 'tenant-a', 'visibility' => ContentVisibility::Private->value, 'status' => ContentStatus::Draft->value]);
    expect(fn () => app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'unauthorized-create', 'scope' => 'site', 'scopeKey' => 'tenant-a', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor))->toThrow(AuthorizationException::class);
    expect(ContentBlock::query()->where('key', 'unauthorized-create')->exists())->toBeFalse();
});
it('requires publish authorization before exposing a live private block', function (): void {
    $system = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'visibility-transition', 'scope' => 'site', 'scopeKey' => 'tenant-a', 'visibility' => ContentVisibility::Private, 'values' => [], 'translations' => ['en' => ['title' => 'Visibility transition']], 'metadata' => []], $system);
    $block = app(ContentBlocks::class)->publish($block, $block->revision, $system);
    $authorization = new class implements ContentAuthorization
    {
        public bool $allowPublish = false;

        /** @var list<array{ability: ContentAbility, context: array<string, mixed>}> */
        public array $calls = [];

        public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
        {
            $this->calls[] = ['ability' => $ability, 'context' => $context];
            if ($ability === ContentAbility::Publish && ! $this->allowPublish) {
                throw new AuthorizationException;
            }
        }
    };
    app()->instance(ContentAuthorization::class, $authorization);
    $actor = new GenericUser(['id' => 'editor-1']);
    expect(fn () => app(ContentBlocks::class)->update($block, ['expectedRevision' => $block->revision, 'mode' => ContentMutationMode::Patch, 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor))->toThrow(AuthorizationException::class);
    expect($block->refresh()->visibility)->toBe(ContentVisibility::Private)->and($block->revision)->toBe(2)->and($authorization->calls)->toHaveCount(2)->and($authorization->calls[0]['ability'])->toBe(ContentAbility::Update)->and($authorization->calls[0]['context'])->toMatchArray(['definition' => 'hero', 'key' => 'visibility-transition', 'scope' => 'site', 'scope_key' => 'tenant-a', 'current_visibility' => ContentVisibility::Private->value, 'target_visibility' => ContentVisibility::Public->value, 'published' => true])->and($authorization->calls[1]['ability'])->toBe(ContentAbility::Publish)->and($authorization->calls[1]['context']['visibility_transition'])->toBeTrue();
    $authorization->allowPublish = true;
    $updated = app(ContentBlocks::class)->update($block, ['expectedRevision' => $block->revision, 'mode' => ContentMutationMode::Patch, 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []], $actor);
    expect($updated->visibility)->toBe(ContentVisibility::Public)->and($updated->revision)->toBe(3);
});
it('fails closed instead of adopting a pre-existing package table', function (string $tableKey, string $migrationFile): void {
    $tableName = "adopted_content_{$tableKey}";
    config()->set("nvl-content.tables.{$tableKey}", $tableName);
    Schema::create($tableName, function (Blueprint $table): void {
        $table->string('sentinel');
    });
    $migration = require __DIR__."/../../database/migrations/{$migrationFile}";
    expect(fn () => $migration->up())->toThrow(LogicException::class)->and(Schema::hasTable($tableName))->toBeTrue()->and(Schema::hasColumn($tableName, 'sentinel'))->toBeTrue();
})->with(['definitions' => ['definitions', '2026_07_28_100001_nvl_content_create_content_definitions_table.php'], 'blocks' => ['blocks', '2026_07_28_100002_nvl_content_create_content_blocks_table.php'], 'translations' => ['blocks_i18n', '2026_07_28_100003_nvl_content_create_content_blocks_i18n_table.php'], 'placements' => ['placements', '2026_07_28_100004_nvl_content_create_content_placements_table.php'], 'revisions' => ['revisions', '2026_07_28_100005_nvl_content_create_content_revisions_table.php']]);
