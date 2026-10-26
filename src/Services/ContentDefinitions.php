<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\ContentFieldPreset;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Exceptions\StaleContentException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentDefinition;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Support\Enums\SystemActor;

/**
 * Native Content workflow family.
 *
 * @api
 */
final class ContentDefinitions
{
    public function __construct(private ContentAuthorization $authorization, private ContentDefinitionRegistry $definitions, private ContentDefinitionMigrationRegistry $migrations, private ContentDefinitionSyncLock $syncLock, private CanonicalJson $json, private ContentPlatformCatalogGuard $platformCatalog, private ContentCodeCatalogUsage $catalogUsage, private ContentBlockDefinitionMigrator $migrator, private ContentFieldPresetRegistry $presets, private ContentSchemaCompiler $compiler, private ContentJsonSchemaBuilder $jsonSchemas) {}

    /** Synchronize the deployment-owned definition catalog after central authorization.
     * @return array{create:list<string>,update:list<string>,unchanged:list<string>,orphan:list<string>,applied:bool}
     */
    public function sync(Authenticatable|SystemActor $actor, bool $dryRun = false): array
    {
        $this->platformCatalog->authorize();
        $this->authorization->authorize(ContentAbility::SyncDefinitions, $actor);
        $registered = [];
        foreach ($this->definitions->all() as $definition) {
            $registered[$definition['key']] = ['definition' => $definition, 'hash' => $this->json->hash($definition)];
        }
        $existing = ContentDefinition::query()->get()->keyBy('key');
        $plan = $this->syncContentDefinitionsPlan($registered, $existing, applied: false);
        if ($dryRun) {
            return $plan;
        }

        return $this->syncLock->run(fn (): array => DB::connection(ContentDefinition::query()->getModel()->getConnectionName())->transaction(function () use ($registered): array {
            $existing = ContentDefinition::query()->orderBy('key')->lockForUpdate()->get()->keyBy('key');
            $plan = $this->syncContentDefinitionsPlan($registered, $existing, applied: true);
            foreach ($registered as $source) {
                $this->syncContentDefinitionsPersist($source['definition'], $source['hash']);
            }
            if ($plan['orphan'] !== []) {
                ContentDefinition::query()->whereIn('key', $plan['orphan'])->update(['is_active' => false, 'orphaned_at' => now()]);
            }

            return $plan;
        }));
    }

    /**
     * @param  array<string, array{definition: array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}, hash: string}>  $registered
     * @param  Collection<string, ContentDefinition>  $existing
     * @return array{create:list<string>,update:list<string>,unchanged:list<string>,orphan:list<string>,applied:bool}
     */
    private function syncContentDefinitionsPlan(array $registered, Collection $existing, bool $applied): array
    {
        $create = [];
        $update = [];
        $unchanged = [];
        foreach ($registered as $key => $source) {
            $model = $existing->get($key);
            if (! $model instanceof ContentDefinition) {
                $create[] = $key;
            } elseif ($model->source_hash !== $source['hash'] || $model->orphaned_at !== null) {
                $this->syncContentDefinitionsAssertVersionProgression($model, $source['definition']);
                $update[] = $key;
            } else {
                $unchanged[] = $key;
            }
        }
        $orphan = $existing->keys()->filter(static fn (string $key): bool => ! isset($registered[$key]))->values()->all();
        sort($create);
        sort($update);
        sort($unchanged);
        sort($orphan);

        return ['create' => $create, 'update' => $update, 'unchanged' => $unchanged, 'orphan' => $orphan, 'applied' => $applied];
    }

    /** Native Content operation.
     * @param  array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}  $definition
     */
    private function syncContentDefinitionsPersist(array $definition, string $hash): void
    {
        ContentDefinition::query()->updateOrCreate(['key' => $definition['key']], ['name' => $definition['name'], 'description' => $definition['description'], 'category' => $definition['category'], 'version' => $definition['version'], 'view' => $definition['view'], 'schema' => $definition['schema'], 'defaults' => $definition['defaults'] === [] ? null : $definition['defaults'], 'allowed_scopes' => $definition['allowedScopes'], 'allowed_regions' => $definition['allowedRegions'], 'is_active' => $definition['isActive'], 'sort_order' => $definition['sortOrder'], 'source_hash' => $hash, 'synced_at' => now(), 'orphaned_at' => null]);
    }

    /** Native Content operation.
     * @param  array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}  $definition
     */
    private function syncContentDefinitionsAssertVersionProgression(ContentDefinition $model, array $definition): void
    {
        if ($definition['version'] < $model->version) {
            throw new InvalidArgumentException("Content definition [{$definition['key']}] cannot decrease from version "."{$model->version} to {$definition['version']}.");
        }
        if ($definition['version'] > $model->version) {
            $versions = ContentOwnership::declared() ? $this->catalogUsage->versions($model->id) : ContentModelRegistry::query()->withTrashed()->where('definition_id', $model->id)->distinct()->pluck('definition_version');
            foreach ($versions as $version) {
                if (! is_int($version) && ! is_string($version)) {
                    throw new InvalidArgumentException("Content definition [{$definition['key']}] has an invalid stored block version.");
                }
                $storedVersion = (int) $version;
                if (! $this->migrations->hasPath($definition['key'], $storedVersion, $definition['version'])) {
                    throw new InvalidArgumentException("Content definition [{$definition['key']}] cannot synchronize to version "."{$definition['version']}; stored blocks at version {$storedVersion} ".'do not have a complete migration path.');
                }
            }
        }
        $storedContract = ['schema' => $model->schema->toArray(), 'defaults' => $model->defaults ?? [], 'allowed_scopes' => $model->allowed_scopes ?? [], 'allowed_regions' => $model->allowed_regions ?? [], 'view' => $model->view];
        $sourceContract = ['schema' => $definition['schema']->toArray(), 'defaults' => $definition['defaults'], 'allowed_scopes' => $definition['allowedScopes'], 'allowed_regions' => $definition['allowedRegions'], 'view' => $definition['view']];
        if ($this->json->hash($storedContract) !== $this->json->hash($sourceContract) && $definition['version'] <= $model->version) {
            throw new InvalidArgumentException("Content definition [{$definition['key']}] must increase its version when its contract changes.");
        }
    }

    /** Plan bounded migrations for the admitted owner’s stored block versions.
     * @return array{definition:string|null,limit:int,totalPending:int,hasMore:bool,ready:list<array{blockId:string,blockKey:string,definition:string,fromVersion:int,toVersion:int,expected_revision:int,deleted:bool,versions:list<int>}>,blocked:list<array{blockId:string,blockKey:string,definition:string,fromVersion:int,toVersion:int,expected_revision:int,deleted:bool,code:string,message:string}>}
     */
    public function planMigration(Authenticatable|SystemActor $actor, ?string $definition = null, ?int $limit = null): array
    {
        $this->authorization->authorize(ContentAbility::MigrateDefinitions, $actor, context: ['definition' => $definition, 'plan' => true]);
        $batchLimit = $this->planContentDefinitionMigrationsBatchLimit($limit);
        $targets = $this->planContentDefinitionMigrationsTargets($definition);
        if ($targets === []) {
            return ['definition' => $definition, 'limit' => $batchLimit, 'totalPending' => 0, 'hasMore' => false, 'ready' => [], 'blocked' => []];
        }
        $query = $this->planContentDefinitionMigrationsPendingQuery($targets);
        $totalPending = (clone $query)->count();
        /** @var Collection<int, ContentBlock> $blocks */
        $blocks = $query->orderBy('id')->limit($batchLimit)->get();
        $ready = [];
        $blocked = [];
        foreach ($blocks as $block) {
            $current = $targets[$block->definition_id] ?? null;
            if (! is_array($current)) {
                continue;
            }
            $this->authorization->authorize(ContentAbility::MigrateDefinitions, $actor, block: $block, context: ['definition' => $current['key'], 'from_version' => $block->definition_version, 'to_version' => $current['version'], 'plan' => true]);
            if (! $this->migrations->hasPath($current['key'], $block->definition_version, $current['version'])) {
                $code = $block->definition_version > $current['version'] ? 'future_version' : 'missing_migration';
                $message = $block->definition_version > $current['version'] ? "Stored version {$block->definition_version} exceeds current version {$current['version']}." : "No complete migration path exists from {$block->definition_version} "."to {$current['version']}.";
                $blocked[] = ['blockId' => $block->id, 'blockKey' => $block->key, 'definition' => $current['key'], 'fromVersion' => $block->definition_version, 'toVersion' => $current['version'], 'expected_revision' => $block->revision, 'deleted' => $block->trashed(), 'code' => $code, 'message' => $message];

                continue;
            }
            $versions = [$block->definition_version];
            foreach ($this->migrations->path($current['key'], $block->definition_version, $current['version']) as $migration) {
                $versions[] = $migration->toVersion();
            }
            $ready[] = ['blockId' => $block->id, 'blockKey' => $block->key, 'definition' => $current['key'], 'fromVersion' => $block->definition_version, 'toVersion' => $current['version'], 'expected_revision' => $block->revision, 'deleted' => $block->trashed(), 'versions' => $versions];
        }

        return ['definition' => $definition, 'limit' => $batchLimit, 'totalPending' => $totalPending, 'hasMore' => $totalPending > $blocks->count(), 'ready' => $ready, 'blocked' => $blocked];
    }

    /** Native Content operation. */
    private function planContentDefinitionMigrationsBatchLimit(?int $requested): int
    {
        $default = ContentConfiguration::positiveInteger('nvl-content.definition_migration.batch_size', 100);
        $maximum = ContentConfiguration::positiveInteger('nvl-content.definition_migration.maximum_batch_size', 1000);
        $limit = $requested ?? $default;
        if ($limit < 1 || $limit > $maximum) {
            throw new InvalidArgumentException("Content definition migration limit must be between 1 and {$maximum}.");
        }

        return $limit;
    }

    /**
     * @return array<string, array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}>
     */
    private function planContentDefinitionMigrationsTargets(?string $only): array
    {
        $definitions = $only === null ? $this->definitions->all() : [$this->definitions->get($only)];
        $mirrors = ContentDefinition::query()->whereIn('key', array_map(static fn (array $definition): string => $definition['key'], $definitions))->get()->keyBy('key');
        $targets = [];
        foreach ($definitions as $definition) {
            $mirror = $mirrors->get($definition['key']);
            if (! $mirror instanceof ContentDefinition) {
                throw new InvalidArgumentException("Content definition [{$definition['key']}] is not synchronized.");
            }
            $sourceHash = $this->json->hash($definition);
            if (! hash_equals($mirror->source_hash, $sourceHash)) {
                throw new InvalidArgumentException("Content definition [{$definition['key']}] is stale; synchronize it before migration.");
            }
            $targets[$mirror->id] = $definition;
        }

        return $targets;
    }

    /**
     * @param  array<string, array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}>  $targets
     * @return Builder<ContentBlock>
     */
    private function planContentDefinitionMigrationsPendingQuery(array $targets): Builder
    {
        return ContentModelRegistry::query()->withTrashed()->where(function (Builder $query) use ($targets): void {
            foreach ($targets as $definitionId => $definition) {
                $query->orWhere(function (Builder $definitionQuery) use ($definition, $definitionId): void {
                    $definitionQuery->where('definition_id', $definitionId)->where('definition_version', '!=', $definition['version']);
                });
            }
        });
    }

    /** Migrate reviewed block versions under their actual source locks.
     * @param  array{definition:string|null,limit:int,totalPending:int,hasMore:bool,ready:list<array{blockId:string,blockKey:string,definition:string,fromVersion:int,toVersion:int,expected_revision:int,deleted:bool,versions:list<int>}>,blocked:list<array{blockId:string,blockKey:string,definition:string,fromVersion:int,toVersion:int,expected_revision:int,deleted:bool,code:string,message:string}>}  $plan
     * @return array{applied:bool,migrated:list<array{blockId:string,definition:string,fromVersion:int,toVersion:int,previousRevision:int,revision:int}>}
     */
    public function migrate(array $plan, Authenticatable|SystemActor $actor): array
    {
        $this->authorization->authorize(ContentAbility::MigrateDefinitions, $actor, context: ['definition' => $plan['definition'], 'count' => count($plan['ready']), 'apply' => true]);
        if ($plan['blocked'] !== []) {
            throw new InvalidArgumentException('Content definition migration plan contains blocked targets and cannot be applied.');
        }
        $maximum = ContentConfiguration::positiveInteger('nvl-content.definition_migration.maximum_batch_size', 1000);
        if (count($plan['ready']) > $maximum) {
            throw new InvalidArgumentException("Content definition migration plan exceeds the {$maximum} block limit.");
        }
        $blockIds = array_map(static fn (array $target): string => $target['blockId'], $plan['ready']);
        if (count($blockIds) !== count(array_unique($blockIds))) {
            throw new InvalidArgumentException('Content definition migration plans cannot contain duplicate block targets.');
        }
        if ($plan['ready'] === []) {
            return ['applied' => true, 'migrated' => []];
        }
        $targets = $plan['ready'];
        usort($targets, static fn (array $left, array $right): int => $left['blockId'] <=> $right['blockId']);
        $attempts = max(1, ContentConfiguration::positiveInteger('nvl-content.definition_migration.transaction_attempts', 3));

        return DB::connection(ContentModelRegistry::newInstance()->getConnectionName())->transaction(function () use ($actor, $targets): array {
            $migrated = [];
            foreach ($targets as $target) {
                $block = ContentModelRegistry::query()->withTrashed()->with(['definition', 'translations'])->lockForUpdate()->findOrFail($target['blockId']);
                $this->authorization->authorize(ContentAbility::MigrateDefinitions, $actor, block: $block, context: ['definition' => $target['definition'], 'from_version' => $target['fromVersion'], 'to_version' => $target['toVersion'], 'apply' => true]);
                if ($block->revision !== $target['expected_revision']) {
                    throw StaleContentException::forRevision($block->id, $target['expected_revision'], $block->revision);
                }
                if ($block->definition->key !== $target['definition'] || $block->definition_version !== $target['fromVersion']) {
                    throw new InvalidArgumentException("Content migration target [{$block->id}] no longer matches its plan.");
                }
                $previousRevision = $block->revision;
                $updated = $this->migrator->migrate($block, $target['toVersion'], $actor);
                $migrated[] = ['blockId' => $updated->id, 'definition' => $target['definition'], 'fromVersion' => $target['fromVersion'], 'toVersion' => $updated->definition_version, 'previousRevision' => $previousRevision, 'revision' => $updated->revision];
            }

            return ['applied' => true, 'migrated' => $migrated];
        }, attempts: $attempts);
    }

    /**
     * @return Collection<int, covariant array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}>
     */
    public function all(Authenticatable|SystemActor $actor): Collection
    {
        $this->authorization->authorize(ContentAbility::ListDefinitions, $actor);

        return collect($this->definitions->all())->filter(static fn (array $definition): bool => $definition['isActive'])->sortBy([['sortOrder', 'asc'], ['key', 'asc']])->values();
    }

    /**
     * Return every registered semantic field preset in deterministic order.
     *
     * @return Collection<int, array{alias:string,name:string,description:string|null,field:ContentFieldDefinition,jsonSchema:array<string, mixed>}>
     */
    public function presets(Authenticatable|SystemActor $actor): Collection
    {
        $this->authorization->authorize(ContentAbility::ListDefinitions, $actor, context: ['presets' => true]);

        return collect($this->presets->all())->map(function (ContentFieldPreset $preset): array {
            $field = $this->compiler->compilePreset($preset);

            return ['alias' => $preset->alias(), 'name' => $preset->name(), 'description' => $preset->description(), 'field' => $field, 'jsonSchema' => $this->jsonSchemas->field($field)];
        })->values();
    }
}
