<?php

declare(strict_types=1);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Events\ContentBlockChanged;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Services\ContentPlacements;
use Nvl\Content\Services\ContentReader;
use Nvl\Content\Services\ContentReferenceRegistry;
use Nvl\Content\Services\ContentSnapshotService;
use Nvl\Content\Tests\Fixtures\OwnershipActor;
use Nvl\Content\Tests\Fixtures\OwnershipCompany;
use Nvl\Content\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Content\Tests\Fixtures\OwnershipContentScenario as Scenario;
use Nvl\Content\Tests\Fixtures\OwnershipReferenceResolver;
use Nvl\Content\Tests\OwnershipTestCase;
use Nvl\Content\Validation\ContentValidationContext;
use Nvl\Ownership\Services\OwnershipAdoptionCoordinator;
use Nvl\Ownership\Services\OwnershipAdoptionStore;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Services\OwnershipWriter;
use Nvl\Ownership\ValueObjects\OwnershipAssignment;
use Nvl\Ownership\ValueObjects\OwnershipOperation;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipQueuedJob;
use Nvl\Support\Ownership\Exceptions\OwnershipSchemaNotReady;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnershipConstraintInspector;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\Services\OwnershipStorageTables;

uses(OwnershipTestCase::class);
it('matches canonical UUID content owner keys as exact text without accepting alternate spellings', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = app(OwnershipWriter::class)->create(OwnershipContentOwner::class, static function (OwnershipAssignment $assignment): OwnershipContentOwner {
            $host = new OwnershipContentOwner;
            $host->forceFill([...$assignment->attributes(), 'id' => 'a1111111-b222-7c33-8d44-e55555555555', 'name' => 'Canonical UUID host'])->save();

            return $host;
        });
        $connection = $host->getConnection();
        if ($connection->getDriverName() === 'mariadb') {
            $column = array_find($connection->getSchemaBuilder()->getColumns($host->getTable()), static fn (array $column): bool => $column['name'] === 'id');
            expect($column['type_name'])->toBe('uuid');
        }
        $placement = Scenario::place(Scenario::block(), $host);
        expect($placement->owner_id)->toBe($host->getKey());
        foreach ([strtoupper($host->getKey()), $host->getKey().' '] as $index => $forgedKey) {
            $row = [...$placement->getRawOriginal(), 'id' => (string) Str::uuid(), 'key' => 'forged-'.$index, 'owner_id' => $forgedKey];
            expect(fn () => $connection->transaction(fn () => $connection->table($placement->getTable())->insert($row)))->toThrow(QueryException::class);
        }
        expect(ContentPlacement::query()->count())->toBe(1);
    });
});
it('refreshes historical Maria UUID triggers through an authorized active plan without discarding data', function (): void {
    $connection = ContentModelRegistry::newInstance()->getConnection();
    if ($connection->getDriverName() !== 'mariadb') {
        $this->markTestSkipped('Historical native Maria UUID trigger refresh requires the MariaDB driver.');
    }
    $company = OwnershipCompany::findOrFail(7);
    $actor = OwnershipActor::firstOrFail();
    [$host, $block] = app(OwnershipRunner::class)->run($company, $actor, static fn (): array => [Scenario::host(), Scenario::block()]);
    $table = (new ContentPlacement)->getTable();
    $grammar = $connection->getQueryGrammar();
    $names = [];
    $morph = $connection->getPdo()->quote($host->getMorphClass());
    $legacy = 'BEGIN IF NEW.`owner_type` IS NULL OR NEW.`owner_id` IS NULL OR NOT ((NEW.`owner_type` = '.$morph.' AND EXISTS (SELECT 1 FROM '.$grammar->wrapTable($host->getTable()).' WHERE CAST(`id` AS BINARY) = CAST(NEW.`owner_id` AS BINARY) AND `owner_scope_key` = NEW.`owner_scope_key`))) THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'NVL ownership partition is invalid or immutable\'; END IF; END';
    foreach (['insert', 'update'] as $event) {
        $name = app(OwnershipConstraintInspector::class)->name($connection, $table, $event, 'polymorphic:owner_type:owner_id');
        $names[] = $name;
        $connection->unprepared('CREATE OR REPLACE TRIGGER '.$grammar->wrap($name).' BEFORE '.strtoupper($event).' ON '.$grammar->wrapTable($table).' FOR EACH ROW '.$legacy);
    }
    $coordinator = app(OwnershipAdoptionCoordinator::class);
    $generation = $connection->table(OwnershipStorageTables::Markers)->where('resource', 'content.placements')->value('generation');
    expect(app(OwnershipAdoptionStore::class)->load($generation)['status'])->toBe('active');
    $previous = $coordinator->resume($generation);
    expect(fn () => $coordinator->activate($previous, new OwnershipOperation('retry-active-content-plan', 'operator', '1')))->toThrow(OwnershipSchemaNotReady::class);
    app(OwnershipRunner::class)->run($company, $actor, static function () use ($host, $block): void {
        expect(fn () => Scenario::place($block, $host))->toThrow(QueryException::class);
    });
    $resources = app(OwnershipResourceRegistry::class);
    $before = [];
    foreach (['fixture.content-owners', 'content.blocks', 'content.translations', 'content.revisions', 'content.placements'] as $resource) {
        $model = new ($resources->get($resource)->model);
        $rows = $connection->table($model->getTable())->orderBy($model->getKeyName())->get();
        $before[$resource] = $rows->map(static fn (object $row): array => (array) $row)->all();
    }
    $operation = new OwnershipOperation('ownership.constraints.refresh', 'operator', '1');
    $coordinator->refreshConstraints($previous, $operation);
    $refreshed = app(OwnershipAdoptionStore::class)->load($previous->id);
    expect($refreshed['status'])->toBe('active')
        ->and($refreshed['plan']->mappingHash)->toBe($previous->mappingHash)
        ->and($connection->table(OwnershipStorageTables::Markers)->where('resource', 'content.placements')->value('generation'))->toBe($generation)
        ->and($connection->table(OwnershipStorageTables::Markers)->where('resource', 'content.placements')->value('state'))->toBe('active');
    foreach ($before as $resource => $rows) {
        $model = new ($resources->get($resource)->model);
        expect($connection->table($model->getTable())->orderBy($model->getKeyName())->get()->map(static fn (object $row): array => (array) $row)->all())->toBe($rows);
    }
    foreach ($names as $name) {
        $trigger = $connection->selectOne('SELECT ACTION_STATEMENT AS body FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?', [$name], false);
        expect($trigger?->body)->toContain('CAST(CAST(`id` AS CHAR) AS BINARY)');
    }
    app(OwnershipRunner::class)->run($company, $actor, static function () use ($host, $block): void {
        $currentHost = OwnershipContentOwner::findOrFail($host->getKey());
        $currentBlock = ContentModelRegistry::query()->findOrFail($block->getKey());
        expect(Scenario::place($currentBlock, $currentHost)->owner_id)->toBe($currentHost->getKey());
    });
});
it('keeps mutable content locale and placement graphs inside one native owner', function (): void {
    $foreign = null;
    $foreignHost = null;
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(8), OwnershipActor::firstOrFail(), function () use (&$foreign, &$foreignHost): void {
        $foreign = Scenario::block();
        $foreignHost = Scenario::host();
        Scenario::place($foreign, $foreignHost);
    });
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function () use ($foreign, $foreignHost): void {
        $block = Scenario::block();
        $host = Scenario::host();
        $placement = Scenario::place($block, $host);
        expect(ContentModelRegistry::query()->count())->toBe(1)->and(ContentModelRegistry::query()->find($foreign->id))->toBeNull();
        $row = $placement->getRawOriginal();
        $row['id'] = (string) Str::uuid();
        $row['key'] = 'foreign';
        $row['content_block_id'] = $foreign->id;
        expect(fn () => DB::transaction(fn () => DB::table($placement->getTable())->insert($row)))->toThrow(QueryException::class);
        $row['content_block_id'] = $block->id;
        $row['owner_id'] = $foreignHost->id;
        expect(fn () => DB::transaction(fn () => DB::table($placement->getTable())->insert($row)))->toThrow(QueryException::class);
    });
});
it('binds immutable snapshots to the full native owner and rejects foreign authority before rendering', function (): void {
    $snapshot = null;
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(8), OwnershipActor::firstOrFail(), function () use (&$snapshot): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        $snapshot = app(ContentSnapshotService::class)->capture($host, 'main', SystemActor::Background);
        expect($snapshot['formatVersion'])->toBe(3)->and($snapshot['ownershipContext']['owner']['key'])->toBe('8');
    });
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function () use ($snapshot): void {
        expect(fn () => app(ContentSnapshotService::class)->render($snapshot, 'en', SystemActor::Background))->toThrow(OwnershipViolation::class);
    });
});
it('deletes an owned host and its canonical placements atomically through composed traits', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        expect($host->delete())->toBeTrue()->and(ContentPlacement::query()->count())->toBe(0);
    });
});
it('preserves cancellation and rolls placement cleanup back with native host deletion', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        $host::deleting(static fn (): bool => false);
        expect($host->delete())->toBeFalse()->and($host->exists)->toBeTrue()->and(ContentPlacement::query()->count())->toBe(1);
    });
});
it('captures native owner authority on scalar committed Content events', function (): void {
    $events = [];
    Event::listen(ContentBlockChanged::class, function ($event) use (&$events): void {
        $events[] = $event;
    });
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), fn () => Scenario::block());
    expect($events)->toHaveCount(1)->and($events[0])->toBeInstanceOf(OwnershipQueuedJob::class)->and($events[0]->ownershipJobEnvelope()->data['owner']['key'])->toBe('7')->and(serialize($events))->not->toContain('Private copy');
});
it('keeps native Content summaries bounded for one and twenty five canonical hosts', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = Scenario::block();
        $hosts = [];
        for ($i = 0; $i < 25; $i++) {
            $host = Scenario::host();
            $hosts[] = $host;
            Scenario::place($block, $host);
        }
        $measure = function (array $hosts): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $result = app(ContentReader::class)->summaries($hosts, 'main', SystemActor::Background);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            expect($result)->toHaveCount(count($hosts));

            return $count;
        };
        $one = $measure([$hosts[0]]);
        expect($measure($hosts))->toBeLessThanOrEqual($one + 2);
    });
});
it('authorizes canonical host facts rather than forged batch handles', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        Scenario::place(Scenario::block(), $host);
        $host->name = 'private';
        $host->save();
        $host->name = 'public';
        app()->instance(ContentAuthorization::class, new class implements ContentAuthorization
        {
            public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
            {
                if ($ability === ContentAbility::ListPlacements && $owner?->getAttribute('name') === 'private') {
                    throw new AuthorizationException('Private host');
                }
            }
        });
        expect(fn () => app(ContentReader::class)->summaries([$host], 'main', SystemActor::Background))->toThrow(AuthorizationException::class);
    });
});
it('retains canonical host and composition identity in raw placement parent writes', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = Scenario::block();
        $first = Scenario::place($block, Scenario::host());
        $second = Scenario::place($block, Scenario::host());
        expect(fn () => DB::transaction(fn () => DB::table($first->getTable())->where('id', $first->id)->update(['parent_id' => $second->id])))->toThrow(QueryException::class);
    });
});
it('deletes owned placement trees from leaves before removing their canonical host', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = Scenario::block();
        $host = Scenario::host();
        $parent = Scenario::place($block, $host);
        app(ContentPlacements::class)->place($block, $host, 'main', ['key' => 'child', 'region' => 'main', 'parentId' => $parent->id], SystemActor::Background);
        expect($host->delete())->toBeTrue()->and(ContentPlacement::query()->count())->toBe(0);
    });
});
it('requires reference adapters to return the exact requested persisted key', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $host = Scenario::host();
        OwnershipReferenceResolver::$record = $host;
        $registry = app(ContentReferenceRegistry::class);
        $registry->register('owned-reference', OwnershipReferenceResolver::class);
        $context = new ContentValidationContext(SystemActor::Background, 'en', 'reference', ContentVisibility::Private);
        expect(fn () => $registry->display('owned-reference', (string) Str::uuid(), $context))->toThrow(OwnershipViolation::class);
    });
});
