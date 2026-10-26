<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Validation\ContentValueValidator;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

/**
 * Captures and re-renders immutable compositions for Templates and other versioned consumers.
 *
 * @internal
 */
final readonly class ContentSnapshotService
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private ContentAuthorization $authorization, private ContentOwnerRegistry $owners, private ContentValueValidator $values, private ContentPatch $patch, private CanonicalJson $json, private Factory $views, private ContentPlacementTree $tree, private ContentMediaReferences $mediaReferences, private ContentLocalePolicy $locales, private ContentPayloadGuard $guard, private ContentIdentityGuard $identities, private ContentLocalizedValues $localizedValues, private ContentPlacementOwnerLock $ownerLocks) {}

    /**
     * Capture one authorized owner group as a bounded immutable snapshot.
     *
     * @return array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}
     */
    public function capture(Model $owner, string $group, Authenticatable|SystemActor $actor, bool $publishing = false): array
    {
        $owner = $this->owners->canonical($owner);
        $ownerType = $this->owners->type($owner);
        $ownerId = $this->owners->id($owner);
        $this->owners->assertGroup($owner, $group);
        $this->authorization->authorize(ContentAbility::Render, $actor, owner: $owner, context: ['group' => $group, 'snapshot' => 'capture', 'publishing' => $publishing]);

        return $this->ownerLocks->run($ownerType, $ownerId, $group, fn (): array => DB::connection((new ContentPlacement)->getConnectionName())->transaction(fn (): array => $this->captureLocked($owner, $ownerType, $ownerId, $group, $actor, $publishing), 3));
    }

    /**
     * Verify and render one immutable owner group snapshot.
     *
     * @param  array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}  $snapshot
     * @return array{ownerType:string,ownerId:string,group:string,locale:string,blocks:list<array{id:string,placementId:string,definitionKey:string,key:string|null,region:string,sortOrder:int,view:string,locale:string,values:array<string, mixed>,fieldTypes:array<string, string>,children:list<array<string,mixed>>}>,regions:array<string, list<array{id:string,placementId:string,definitionKey:string,key:string|null,region:string,sortOrder:int,view:string,locale:string,values:array<string, mixed>,fieldTypes:array<string, string>,children:list<array<string,mixed>>}>>,version:string}
     */
    public function render(array $snapshot, string $locale, Authenticatable|SystemActor|PublicActor $actor): array
    {
        $native = ContentOwnership::snapshotContext();
        if ($native !== null || $snapshot['formatVersion'] >= 3 || $snapshot['ownershipContext'] !== null) {
            if ($native === null || $snapshot['formatVersion'] !== 3 || $snapshot['tenantId'] !== null || $snapshot['ownershipContext'] === null || $this->json->hash($native) !== $this->json->hash($snapshot['ownershipContext'])) {
                throw new OwnershipViolation('Content snapshot ownership differs from the admitted native owner.');
            }
        }
        if ($snapshot['tenantId'] !== null) {
            throw new OwnershipViolation('Legacy tenant snapshots require explicit native adoption.');
        }
        $this->identities->group($snapshot['group']);
        $this->assertSize($snapshot['ownerType'], $snapshot['ownerId'], $snapshot['group'], $snapshot['blocks']);
        if (! hash_equals($snapshot['version'], $this->version($snapshot['ownerType'], $snapshot['ownerId'], $snapshot['group'], $snapshot['blocks'], $snapshot['tenantId'], $snapshot['formatVersion'], $snapshot['ownershipContext']))) {
            throw new InvalidArgumentException('Content composition snapshot integrity check failed.');
        }
        $locale = $this->locales->assertSupported($locale);
        $owner = $this->owners->resolve($snapshot['ownerType'], $snapshot['ownerId']);
        $this->owners->assertGroup($owner, $snapshot['group']);
        $this->authorization->authorize(ContentAbility::Render, $actor, owner: $owner, context: ['group' => $snapshot['group'], 'snapshot' => 'render']);
        $records = [];
        foreach ($snapshot['blocks'] as $record) {
            if (isset($records[$record['placementId']])) {
                throw new InvalidArgumentException('Content snapshot placement IDs must be unique strings.');
            }
            $records[$record['placementId']] = $record;
        }
        $this->assertTree($records);
        $resources = new ContentRenderResources;
        $resources->preloadMedia($this->snapshotMediaIds($records, $locale));
        $children = [];
        foreach ($records as $record) {
            $parentKey = $record['parentId'] ?? '*';
            $children[$parentKey] ??= [];
            $children[$parentKey][] = $record;
        }
        $visited = [];
        $roots = [];
        foreach ($children['*'] ?? [] as $record) {
            $roots[] = $this->renderNode($record, $children, $locale, $actor, $resources, $owner, $snapshot['group'], $visited, 1);
        }
        usort($roots, static fn (array $left, array $right): int => [$left['region'], $left['sortOrder'], $left['placementId']] <=> [$right['region'], $right['sortOrder'], $right['placementId']]);
        $regions = [];
        foreach ($roots as $block) {
            $regions[$block['region']] ??= [];
            $regions[$block['region']][] = $block;
        }
        ksort($regions);

        return ['ownerType' => $snapshot['ownerType'], 'ownerId' => $snapshot['ownerId'], 'group' => $snapshot['group'], 'locale' => $locale, 'blocks' => $roots, 'regions' => $regions, 'version' => $snapshot['version']];
    }

    /** Native Content operation.
     * @return array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}
     */
    private function captureLocked(Model $owner, string $ownerType, string $ownerId, string $group, Authenticatable|SystemActor $actor, bool $publishing): array
    {
        $this->owners->id($owner);
        $placements = $this->tree->eligible($this->tree->load($owner, $group, lockForUpdate: true), publicOnly: false);
        $blocks = [];
        foreach ($placements as $placement) {
            $block = $placement->block;
            $translations = [];
            foreach ($block->translations as $translation) {
                $locale = $translation->getAttribute('locale');
                $values = $translation->getAttribute('values');
                if (is_string($locale) && is_array($values)) {
                    $translations[$locale] = ContentArrays::stringMap($values, "content snapshot translation {$locale}");
                }
            }
            ksort($translations);
            $baseValues = is_array($block->values) ? ContentArrays::stringMap($block->values, "content snapshot block {$block->id} values") : [];
            $overrides = ContentArrays::stringMap(is_array($placement->overrides) ? $placement->overrides : [], "content snapshot placement {$placement->id} overrides");
            if ($publishing) {
                $validated = $this->values->validate($block->definition_schema, $this->patch->merge($baseValues, $overrides), $translations, $actor, $block->visibility, publishing: true, owner: $owner, group: $group);
                $baseValues = $validated->values;
                $translations = $validated->translations;
                $overrides = [];
            }
            /** @var array<string,mixed> $serializedSchema Canonical encoding of the schema invariant always yields an object. */
            $serializedSchema = json_decode($this->json->encode($block->definition_schema), true, flags: JSON_THROW_ON_ERROR);
            $blocks[] = ['placementId' => $placement->id, 'parentId' => $placement->parent_id, 'key' => $placement->key, 'region' => $placement->region, 'sortOrder' => $placement->sort_order, 'blockId' => $block->id, 'definitionKey' => $block->definition->key, 'definitionSchema' => $serializedSchema, 'definitionView' => $block->definition_view, 'visibility' => $block->visibility->value, 'values' => $baseValues, 'translations' => $translations, 'overrides' => $overrides, 'blockRevision' => $block->revision, 'placementRevision' => $placement->revision];
        }
        $this->assertSize($ownerType, $ownerId, $group, $blocks);
        $tenantId = null;
        $native = ContentOwnership::snapshotContext();
        $format = $native !== null ? 3 : 1;

        return ['ownerType' => $ownerType, 'ownerId' => $ownerId, 'group' => $group, 'blocks' => $blocks, 'version' => $this->version($ownerType, $ownerId, $group, $blocks, $tenantId, $format, $native), 'tenantId' => $tenantId, 'formatVersion' => $format, 'ownershipContext' => $native];
    }

    /**
     * @param  array<string, list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>>  $children
     * @param  array<string, true>  $visited
     * @param  array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}  $record
     * @return array{id:string,placementId:string,definitionKey:string,key:string|null,region:string,sortOrder:int,view:string,locale:string,values:array<string, mixed>,fieldTypes:array<string, string>,children:list<array<string,mixed>>}
     */
    private function renderNode(array $record, array $children, string $locale, Authenticatable|SystemActor|PublicActor $actor, ContentRenderResources $resources, Model $owner, string $group, array &$visited, int $depth): array
    {
        $placementId = $record['placementId'];
        $maximumDepth = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_depth', 50);
        if ($depth > $maximumDepth || isset($visited[$placementId])) {
            throw new InvalidArgumentException('Content snapshot tree contains an invalid placement or cycle.');
        }
        $visited[$placementId] = true;
        $schema = ContentSchema::fromArray($record['definitionSchema']);
        $base = ContentArrays::stringMap($record['values'], "content snapshot {$placementId} values");
        $translations = ContentArrays::translations($record['translations'], "content snapshot {$placementId} translations");
        $overrides = ContentArrays::stringMap($record['overrides'], "content snapshot {$placementId} overrides");
        $merged = $this->patch->merge($base, $overrides);
        $merged = $this->localizedValues->overlay($schema, $merged, $this->localizedValues->resolve($schema, $translations, $locale));
        $view = $record['definitionView'] !== null && $record['definitionView'] !== '' ? $record['definitionView'] : config('nvl-content.rendering.default_view', 'nvl-content::blocks.default');
        if (! is_string($view) || $view === '') {
            throw new InvalidArgumentException('Content snapshot has no renderable view.');
        }
        if ((bool) config('nvl-content.rendering.strict_views', true) && ! $this->views->exists($view)) {
            throw new InvalidArgumentException("Content block view [{$view}] does not exist.");
        }
        $renderedChildren = [];
        foreach ($children[$placementId] ?? [] as $child) {
            $renderedChildren[] = $this->renderNode($child, $children, $locale, $actor, $resources, $owner, $group, $visited, $depth + 1);
        }
        unset($visited[$placementId]);

        return ['id' => $record['blockId'], 'placementId' => $placementId, 'definitionKey' => $record['definitionKey'], 'key' => $record['key'], 'region' => $record['region'], 'sortOrder' => $record['sortOrder'], 'view' => $view, 'locale' => $locale, 'values' => $this->values->render($schema, $merged, $actor, $locale, ContentVisibility::from($record['visibility']), $resources, $owner, group: $group), 'fieldTypes' => $schema->fieldTypes(), 'children' => $renderedChildren];
    }

    /**
     * @param  list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>  $blocks
     */
    private function assertSize(string $ownerType, string $ownerId, string $group, array $blocks): void
    {
        $maximumPlacements = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);
        if (count($blocks) > $maximumPlacements) {
            throw new InvalidArgumentException("Content snapshot exceeds the {$maximumPlacements} placement limit.");
        }
        $maximum = ContentConfiguration::positiveInteger('nvl-content.validation.maximum_snapshot_bytes', 2097152);
        $payload = ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group, 'blocks' => $this->serializeBlocks($blocks)];
        $this->guard->json($payload, 'Content composition snapshot', $maximum, ContentConfiguration::positiveInteger('nvl-content.validation.maximum_snapshot_depth', 32));
    }

    /**
     * @param  list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>  $blocks
     * @param  array<string,mixed>|null  $ownershipContext
     */
    private function version(string $ownerType, string $ownerId, string $group, array $blocks, ?string $tenantId = null, int $formatVersion = 1, ?array $ownershipContext = null): string
    {
        $payload = ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group, 'blocks' => $this->serializeBlocks($blocks)];
        if ($formatVersion >= 3) {
            $payload = ['format_version' => $formatVersion, 'ownership_context' => $ownershipContext, ...$payload];
        } elseif ($formatVersion >= 2) {
            $payload = ['format_version' => $formatVersion, 'tenant_id' => $tenantId, ...$payload];
        }

        return $this->json->hash($payload);
    }

    /**
     * @param  array<string, array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>  $records
     * @return list<string>
     */
    private function snapshotMediaIds(array $records, string $locale): array
    {
        $identifiers = [];
        foreach ($records as $placementId => $record) {
            $schema = ContentSchema::fromArray($record['definitionSchema']);
            $values = $this->patch->merge(ContentArrays::stringMap($record['values'], "content snapshot {$placementId} values"), ContentArrays::stringMap($record['overrides'], "content snapshot {$placementId} overrides"));
            $translations = ContentArrays::translations($record['translations'], "content snapshot {$placementId} translations");
            $values = $this->localizedValues->overlay($schema, $values, $this->localizedValues->resolve($schema, $translations, $locale));
            foreach ($this->mediaReferences->extract($schema, $values, $locale) as $reference) {
                $identifiers[] = $reference['id'];
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * @param  array<string, array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>  $records
     */
    private function assertTree(array $records): void
    {
        $maximumDepth = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_depth', 50);
        foreach ($records as $placementId => $record) {
            $parentId = $record['parentId'];
            $region = $record['region'];
            $visited = [$placementId => true];
            for ($depth = 1; $parentId !== null; $depth++) {
                if ($depth >= $maximumDepth) {
                    throw new InvalidArgumentException("Content snapshot depth exceeds {$maximumDepth} levels.");
                }
                $parent = $records[$parentId] ?? null;
                if (! is_array($parent)) {
                    throw new InvalidArgumentException("Content snapshot placement [{$placementId}] references a missing parent.");
                }
                if ($parent['region'] !== $region) {
                    throw new InvalidArgumentException('Nested content snapshot placements must remain in their parent region.');
                }
                if (isset($visited[$parentId])) {
                    throw new InvalidArgumentException('Content snapshot placement cycles are not allowed.');
                }
                $visited[$parentId] = true;
                $parentId = $parent['parentId'];
            }
        }
    }

    /**
     * Return the canonical JSON-safe snapshot block representation.
     *
     * @param  list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>  $blocks
     * @return list<array<string, mixed>>
     */
    private function serializeBlocks(array $blocks): array
    {
        return array_map(static fn (array $block): array => $block, $blocks);
    }
}
