<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\ContentBlockQueryScope;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Exceptions\ContentScopeOverflowException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Content\Relations\ExactTextValueComparison;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Media\Services\MediaModelRegistry;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use RuntimeException;

/**
 * Native Content workflow family.
 *
 * @api
 */
final class ContentReader
{
    public function __construct(private ContentAuthorization $authorization, private ContentIdentityGuard $identities, private ContentOwnerRegistry $owners, private OwnershipBoundary $boundary, private ContentLocalePolicy $locales, private ContentLocalizedValues $localizedValues, private ContentCatalogCopyRegistry $copies, private ContentMediaReferences $media, private ContentSnapshotIntegrity $integrity) {}

    /** Read an authorized native composition through the existing rendering engine.
     * @param  array{actor?: Authenticatable|SystemActor, group?: string, locale?: string, publicOnly?: bool}  $context
     * @return array<string,mixed>
     */
    public function composition(Model $owner, array $context = []): array
    {
        return app(ContentRenderer::class)->render($owner, $context);
    }

    /** Export the durable, checksummed content facts for one admitted owner group.
     * @return array<string,mixed>
     */
    public function exportSnapshot(Model $owner, string $group, Authenticatable|SystemActor $actor): array
    {
        return app(ContentSnapshotService::class)->capture($owner, $group, $actor);
    }

    /** Resolve the exact admitted block and authorize its read. */
    public function get(ContentBlock|string $block, Authenticatable|SystemActor $actor): ContentBlock
    {
        $blockId = $block instanceof ContentBlock ? $block->id : $block;
        $model = ContentModelRegistry::query()->with(['definition', 'translations'])->findOrFail($blockId);
        $this->authorization->authorize(ContentAbility::View, $actor, $model);

        return $model;
    }

    /**
     * Return one authorized editable block with an exact unambiguous key.
     */
    public function find(string $key, Authenticatable|SystemActor $actor): ContentBlock
    {
        $this->identities->blockKey($key);
        $query = ContentModelRegistry::query()->with(['definition', 'translations']);
        $driver = $query->getModel()->getConnection()->getDriverName();
        $wrappedKey = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn('key'));
        $blocks = $query->whereRaw(new ExactTextValueComparison($wrappedKey, $driver), [$key, $key])->orderBy('id')->limit(2)->get();
        if ($blocks->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(ContentModelRegistry::modelClass(), [$key]);
        }
        foreach ($blocks as $block) {
            $this->authorization->authorize(ContentAbility::View, $actor, $block);
        }
        if ($blocks->count() !== 1) {
            throw new InvalidArgumentException("Content block key [{$key}] is ambiguous across active scopes.");
        }

        return $blocks->firstOrFail();
    }

    /**
     * Return one editable placement by exact ID or key inside its owner group.
     */
    public function placement(Model $owner, string $group, string $idOrKey, Authenticatable|SystemActor $actor): ContentPlacement
    {
        $owner = $this->owners->canonical($owner);
        $ownerType = $this->owners->type($owner);
        $ownerId = $this->owners->id($owner);
        $this->owners->assertGroup($owner, $group);
        $this->identities->placementKey($idOrKey);
        $this->authorization->authorize(ContentAbility::ListPlacements, $actor, owner: $owner, context: ['group' => $group, 'includes_blocks' => true]);
        $query = ContentPlacement::query()->with(['block' => static function (Relation $query): void {
            $query->select(['id', 'definition_id', 'key', 'scope', 'scope_key', 'status', 'visibility', 'values', 'metadata', 'definition_version', 'revision', 'published_at']);
        }, 'block.definition' => static function (Relation $query): void {
            $query->select(['id', 'key']);
        }, 'block.translations' => static function (Relation $query): void {
            $query->select(['id', 'content_block_id', 'locale', 'values']);
        }])->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('group', $group);
        $driver = $query->getModel()->getConnection()->getDriverName();
        $wrappedKey = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn('key'));
        $exactKey = new ExactTextValueComparison($wrappedKey, $driver);
        if (Str::isUuid($idOrKey)) {
            $query->where(static function (Builder $query) use ($exactKey, $idOrKey): void {
                $query->whereKey($idOrKey)->orWhere(static function (Builder $query) use ($exactKey, $idOrKey): void {
                    $query->whereRaw($exactKey, [$idOrKey, $idOrKey]);
                });
            });
        } else {
            $query->whereRaw($exactKey, [$idOrKey, $idOrKey]);
        }
        $placements = $query->orderBy('id')->limit(2)->get();
        if ($placements->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(ContentPlacement::class, [$idOrKey]);
        }
        if ($placements->count() !== 1) {
            throw new InvalidArgumentException("Content placement identity [{$idOrKey}] is ambiguous in the owner group.");
        }

        return $placements->firstOrFail();
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int, ContentBlock>
     */
    public function paginate(array $filters, Authenticatable|SystemActor $actor, int $perPage = 25): LengthAwarePaginator
    {
        $this->authorization->authorize(ContentAbility::List, $actor);
        $query = ContentModelRegistry::query()->with(['definition', 'translations']);
        if ($this->authorization instanceof ContentBlockQueryScope) {
            $this->authorization->scopeContentBlocks($query, $actor);
        }
        foreach (array_intersect_key($filters, array_flip(['key', 'scope', 'scope_key', 'status', 'visibility', 'definition_id'])) as $column => $value) {
            $query->where($column, $value);
        }
        $query->orderByDesc('updated_at')->orderBy('id');
        $blocks = $query->paginate(max(1, min(100, $perPage)));

        return $blocks->through(static fn (ContentBlock $block): ContentBlock => $block);
    }

    /**
     * Return the owner’s existing group keys in deterministic order.
     *
     * @return Collection<int, string>
     */
    public function groups(Model $owner, Authenticatable|SystemActor $actor): Collection
    {
        $this->authorization->authorize(ContentAbility::ListPlacements, $actor, owner: $owner, context: ['groups' => true]);

        return collect($this->owners->groups($owner));
    }

    /**
     * Return every placement fact in one authorized owner group.
     *
     * @return Collection<int, ContentPlacement>
     */
    public function placements(Model $owner, string $group, Authenticatable|SystemActor $actor, bool $includeBlocks = false): Collection
    {
        $this->owners->assertGroup($owner, $group);
        $ownerId = $owner->getKey();
        if (! is_int($ownerId) && ! is_string($ownerId)) {
            throw new InvalidArgumentException('Content owners require a scalar persisted identity.');
        }
        $context = ['group' => $group];
        if ($includeBlocks) {
            $context['includes_blocks'] = true;
        }
        $this->authorization->authorize(ContentAbility::ListPlacements, $actor, owner: $owner, context: $context);
        $maximum = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);
        $query = ContentPlacement::query()->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $ownerId)->where('group', $group)->orderBy('region')->orderBy('sort_order')->orderBy('id')->limit($maximum + 1);
        if ($includeBlocks) {
            $query->with(['block' => static function (Relation $query): void {
                $query->select(['id', 'definition_id', 'key', 'scope', 'scope_key', 'status', 'visibility', 'values', 'metadata', 'definition_version', 'revision', 'published_at']);
            }, 'block.definition' => static function (Relation $query): void {
                $query->select(['id', 'key']);
            }, 'block.translations' => static function (Relation $query): void {
                $query->select(['id', 'content_block_id', 'locale', 'values']);
            }]);
        }
        /** @var Collection<int, ContentPlacement> $placements */
        $placements = $query->get();
        if ($placements->count() > $maximum) {
            throw new InvalidArgumentException("Content owner exceeds the {$maximum} placement limit.");
        }

        return $placements->map(static fn (ContentPlacement $placement): ContentPlacement => $placement);
    }

    private const int MAXIMUM_OWNERS = 100;

    /**
     * Return deterministic placement summaries keyed by canonical owner identity.
     *
     * @param  iterable<array-key, Model>  $owners
     * @return array<string, list<ContentPlacement>>
     */
    public function summaries(iterable $owners, string $group, Authenticatable|SystemActor $actor): array
    {
        $normalized = $this->listOwnerContentPlacementSummariesNormalize($owners, $group);
        if ($normalized === []) {
            return [];
        }
        if (ContentOwnership::declared()) {
            $normalized = $this->listOwnerContentPlacementSummariesCanonicalOwners($normalized);
        }
        foreach ($normalized as $identity) {
            $this->authorization->authorize(ContentAbility::ListPlacements, $actor, owner: $identity['owner'], context: ['group' => $group, 'includes_blocks' => true]);
        }
        if (! ContentOwnership::declared()) {
            $this->listOwnerContentPlacementSummariesAssertPersisted($normalized);
        }
        $maximum = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);
        /** @var array<string, list<ContentPlacement>> $summaries */
        $summaries = [];
        /** @var array<string, list<array{owner: Model, type: string, id: string, model: class-string<Model>}>> $ownersByType */
        $ownersByType = [];
        foreach ($normalized as $identity) {
            $summaries[$this->listOwnerContentPlacementSummariesSummaryKey($identity['type'], $identity['id'])] = [];
            $ownersByType[$identity['type']][] = $identity;
        }
        foreach ($ownersByType as $type => $identities) {
            $ownerIds = array_map(static fn (array $identity): string => $identity['id'], $identities);
            $queryLimit = $this->listOwnerContentPlacementSummariesQueryLimit($maximum, count($ownerIds));
            $placements = ContentPlacement::query()->select(['id', 'content_block_id', 'owner_type', 'owner_id', 'group', 'key', 'parent_id', 'region', 'sort_order', 'is_visible', 'overrides', 'revision'])->with(['block' => static function (Relation $query): void {
                $query->select(ContentModelRegistry::projectionColumns(['id', 'definition_id', 'key', 'scope', 'scope_key', 'status', 'visibility', 'values', 'metadata', 'definition_version', 'revision', 'published_at']));
            }, 'block.definition' => static function (Relation $query): void {
                $query->select(['id', 'key']);
            }, 'block.translations' => static function (Relation $query): void {
                $query->select(['id', 'content_block_id', 'locale', 'values']);
            }])->where('owner_type', $type)->whereIn('owner_id', $ownerIds)->where('group', $group)->orderBy('owner_id')->orderBy('region')->orderBy('sort_order')->orderBy('id')->limit($queryLimit)->get();
            if ($placements->count() === $queryLimit) {
                throw new InvalidArgumentException("A Content owner exceeds the {$maximum} placement limit.");
            }
            /** @var array<string, list<ContentPlacement>> $placementsByOwner */
            $placementsByOwner = [];
            foreach ($placements as $placement) {
                $placementsByOwner[$placement->owner_id][] = $placement;
            }
            foreach ($ownerIds as $ownerId) {
                $ownerPlacements = $placementsByOwner[$ownerId] ?? [];
                if (count($ownerPlacements) > $maximum) {
                    throw new InvalidArgumentException("Content owner exceeds the {$maximum} placement limit.");
                }
                $summaries[$this->listOwnerContentPlacementSummariesSummaryKey($type, $ownerId)] = $ownerPlacements;
            }
        }

        return $summaries;
    }

    /**
     * @param  iterable<array-key, Model>  $owners
     * @return list<array{owner: Model, type: string, id: string, model: class-string<Model>}>
     */
    private function listOwnerContentPlacementSummariesNormalize(iterable $owners, string $group): array
    {
        $normalized = [];
        $identities = [];
        $entries = 0;
        foreach ($owners as $candidate) {
            $entries++;
            if ($entries > self::MAXIMUM_OWNERS) {
                throw new InvalidArgumentException('Content placement summaries support at most 100 owner entries.');
            }
            $owner = $this->listOwnerContentPlacementSummariesNormalizeOwner($candidate);
            if (! $owner->exists) {
                throw new InvalidArgumentException('A Content owner must be persisted.');
            }
            $type = $this->owners->type($owner);
            $identifier = $owner->getKey();
            if (! is_int($identifier) && ! is_string($identifier)) {
                throw new InvalidArgumentException('A Content owner identifier must be a string or integer.');
            }
            $id = (string) $identifier;
            $this->identities->owner($type, $id);
            $this->owners->assertGroup($owner, $group);
            $identity = $type.':'.$id;
            if (isset($identities[$identity])) {
                continue;
            }
            $identities[$identity] = true;
            $normalized[] = ['owner' => $owner, 'type' => $type, 'id' => $id, 'model' => $this->owners->model($type)];
        }

        return $normalized;
    }

    /**
     * Return the non-numeric serialization-safe key for one canonical owner identity.
     */
    private function listOwnerContentPlacementSummariesSummaryKey(string $type, string $id): string
    {
        return $type.':'.$id;
    }

    /**
     * Return one overflow-detecting query limit without risking integer overflow.
     */
    private function listOwnerContentPlacementSummariesQueryLimit(int $maximum, int $owners): int
    {
        if ($maximum > intdiv(PHP_INT_MAX - 1, $owners)) {
            throw new InvalidArgumentException('The configured Content placement limit is too large for bulk summaries.');
        }

        return $maximum * $owners + 1;
    }

    /**
     * Validate one runtime iterable entry while preserving the public iterable contract.
     */
    private function listOwnerContentPlacementSummariesNormalizeOwner(mixed $owner): Model
    {
        if (! $owner instanceof Model) {
            throw new InvalidArgumentException('Content placement summary owners must be Eloquent ContentOwner models.');
        }

        return $owner;
    }

    /** Canonically reload each native host class once before consuming host authorization facts.
     * @param  list<array{owner:Model,type:string,id:string,model:class-string<Model>}>  $identities
     * @return list<array{owner:Model,type:string,id:string,model:class-string<Model>}>
     */
    private function listOwnerContentPlacementSummariesCanonicalOwners(array $identities): array
    {
        $groups = [];
        foreach ($identities as $identity) {
            $groups[$identity['model']][] = $identity['id'];
        }
        $loaded = [];
        foreach ($groups as $class => $ids) {
            $prototype = new $class;
            $query = $this->boundary->query($prototype->newQuery(), ContentOwnership::resource($prototype));
            foreach ($query->whereKey($ids)->get() as $record) {
                if ($record::class !== $class) {
                    throw new InvalidArgumentException('Content host hydration must retain its canonical capability class.');
                }
                $key = $record->getKey();
                if (! is_int($key) && ! is_string($key)) {
                    throw new InvalidArgumentException('Canonical Content hosts require native scalar identities.');
                }
                $loaded[$class][(string) $key] = $record;
            }
        }
        foreach ($identities as &$identity) {
            $identity['owner'] = $loaded[$identity['model']][$identity['id']] ?? throw new InvalidArgumentException('A Content host is outside the admitted native owner.');
        }
        unset($identity);

        return $identities;
    }

    /**
     * @param  list<array{owner: Model, type: string, id: string, model: class-string<Model>}>  $owners
     */
    private function listOwnerContentPlacementSummariesAssertPersisted(array $owners): void
    {
        /** @var array<class-string<Model>, list<array{owner: Model, type: string, id: string, model: class-string<Model>}>> $ownersByModel */
        $ownersByModel = [];
        foreach ($owners as $identity) {
            $ownersByModel[$identity['model']][] = $identity;
        }
        foreach ($ownersByModel as $modelClass => $identities) {
            /** @var Model $model */
            $model = new $modelClass;
            $ids = array_map(static fn (array $identity): string => $identity['id'], $identities);
            $persistedIds = $model->newQuery()->whereKey($ids)->pluck($model->getKeyName())->all();
            /** @var array<string, true> $persisted */
            $persisted = [];
            foreach ($persistedIds as $persistedId) {
                if (! is_int($persistedId) && ! is_string($persistedId)) {
                    throw new InvalidArgumentException('A persisted Content owner identifier must be a string or integer.');
                }
                $persisted[(string) $persistedId] = true;
            }
            foreach ($ids as $id) {
                if (! isset($persisted[$id])) {
                    throw new InvalidArgumentException("Content owner [{$id}] no longer exists.");
                }
            }
        }
    }

    /**
     * Resolve unique block keys using the first matching scope as highest priority.
     *
     * @param  list<array{scope:string,scopeKey:string}>  $scopes
     * @return array{locale:string,scopes:list<array{scope:string,scopeKey:string}>,values:array<string, array<string, mixed>>,sources:array<string, string>,matched:int,limit:int}
     */
    public function resolveScopes(array $scopes, string $locale, Authenticatable|SystemActor $actor, ?int $limit = null, bool $publicOnly = true): array
    {
        $scopes = $this->resolveContentScopesValidatedScopes($scopes);
        $locale = $this->locales->assertSupported($locale);
        $limit = $this->resolveContentScopesLimit($limit);
        $this->authorization->authorize(ContentAbility::Render, $actor, context: ['scopes' => array_map(static fn (array $scope): array => ['scope' => $scope['scope'], 'scope_key' => $scope['scopeKey']], $scopes), 'locale' => $locale, 'public_only' => $publicOnly, 'limit' => $limit]);
        $query = ContentModelRegistry::query()->with(['definition', 'translations'])->published()->where(function (Builder $query) use ($scopes): void {
            foreach ($scopes as $scope) {
                $query->orWhere(function (Builder $query) use ($scope): void {
                    $query->where('scope', $scope['scope'])->where('scope_key', $scope['scopeKey']);
                });
            }
        })->orderBy('key')->orderBy('id');
        if ($publicOnly) {
            $query->public();
        }
        if ($this->authorization instanceof ContentBlockQueryScope) {
            $this->authorization->scopeContentBlocks($query, $actor);
        }
        /** @var Collection<int, ContentBlock> $blocks */
        $blocks = $query->limit($limit + 1)->get();
        if ($blocks->count() > $limit) {
            throw new ContentScopeOverflowException("Content scope resolution exceeds the configured limit of {$limit} blocks.");
        }
        $byScope = $blocks->groupBy(static fn (ContentBlock $block): string => self::resolveContentScopesScopeIdentifier($block->scope, $block->scope_key));
        $values = [];
        $sources = [];
        foreach ($scopes as $scope) {
            $identifier = self::resolveContentScopesScopeIdentifier($scope['scope'], $scope['scopeKey']);
            foreach ($byScope->get($identifier, collect()) as $block) {
                if (array_key_exists($block->key, $values)) {
                    continue;
                }
                $values[$block->key] = $this->resolveContentScopesLocalizedBlockValues($block, $locale);
                $sources[$block->key] = $identifier;
            }
        }
        ksort($values);
        ksort($sources);

        return ['locale' => $locale, 'scopes' => $scopes, 'values' => $values, 'sources' => $sources, 'matched' => $blocks->count(), 'limit' => $limit];
    }

    /**
     * @param  array<array-key, mixed>  $scopes
     * @return list<array{scope:string,scopeKey:string}>
     */
    private function resolveContentScopesValidatedScopes(array $scopes): array
    {
        if ($scopes === []) {
            throw new InvalidArgumentException('Content scope resolution requires at least one scope.');
        }
        $maximumScopes = ContentConfiguration::positiveInteger('nvl-content.rendering.scope_resolution.maximum_scopes', 25);
        if (count($scopes) > $maximumScopes) {
            throw new InvalidArgumentException("Content scope resolution accepts at most {$maximumScopes} scopes.");
        }
        $validated = [];
        $seen = [];
        foreach ($scopes as $scope) {
            if (! is_array($scope) || ! is_string($scope['scope'] ?? null) || ! is_string($scope['scopeKey'] ?? null)) {
                throw new InvalidArgumentException('Content scopes must contain only ContentScopeData instances.');
            }
            $scopeName = trim($scope['scope']);
            $scopeKey = trim($scope['scopeKey']);
            if (preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $scopeName) !== 1 || $scopeKey === '' || mb_strlen($scopeKey) > 191) {
                throw new InvalidArgumentException('Content scope names and keys must satisfy the persisted identity bounds.');
            }
            $identifier = self::resolveContentScopesScopeIdentifier($scopeName, $scopeKey);
            if (isset($seen[$identifier])) {
                throw new InvalidArgumentException("Content scope [{$identifier}] is duplicated.");
            }
            $seen[$identifier] = true;
            $validated[] = ['scope' => $scopeName, 'scopeKey' => $scopeKey];
        }

        return $validated;
    }

    /** Native Content operation. */
    private function resolveContentScopesLimit(?int $requested): int
    {
        $default = ContentConfiguration::positiveInteger('nvl-content.rendering.scope_resolution.limit', 250);
        $maximum = ContentConfiguration::positiveInteger('nvl-content.rendering.scope_resolution.maximum_limit', 1000);
        $limit = $requested ?? $default;
        if ($limit < 1 || $limit > $maximum) {
            throw new InvalidArgumentException("Content scope resolution limit must be between 1 and {$maximum}.");
        }

        return $limit;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContentScopesLocalizedBlockValues(ContentBlock $block, string $locale): array
    {
        $base = is_array($block->values) ? ContentArrays::stringMap($block->values, "content block {$block->id} values") : [];
        $translations = [];
        foreach ($block->translations as $translation) {
            $translationLocale = $translation->getAttribute('locale');
            $translationValues = $translation->getAttribute('values');
            if (is_string($translationLocale) && is_array($translationValues)) {
                $translations[$translationLocale] = ContentArrays::stringMap($translationValues, "content block {$block->id} translation {$translationLocale}");
            }
        }

        return $this->localizedValues->overlay($block->definition_schema, $base, $this->localizedValues->resolve($block->definition_schema, $translations, $locale));
    }

    /** Native Content operation. */
    private static function resolveContentScopesScopeIdentifier(string $scope, string $scopeKey): string
    {
        return "{$scope}:{$scopeKey}";
    }

    /** Export one exact capability-authorized immutable snapshot and its Media identities.
     * @return array{ownerAlias:string,ownerId:string,group:string,grantId:string,sourceRevision:int,sourceHash:string,snapshot:array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null},mediaIds:list<string>}
     */
    public function exportForCopy(string $ownerAlias, string $ownerId, string $group, string $grantId): array
    {
        $access = $this->copies->resolve($ownerAlias);
        $owner = $access->canonicalSource($ownerId, $group, $grantId);
        if ($owner::class !== $this->owners->model($ownerAlias) || $owner->getKey() !== $ownerId) {
            throw new OwnershipViolation('The catalog authorizer returned another source identity.');
        }
        $snapshot = $owner->getAttribute('content_snapshot');
        $revision = $owner->getAttribute('revision');
        if (! is_array($snapshot) || ! is_int($revision)) {
            throw new RuntimeException('Catalog Content owner has no immutable snapshot.');
        }
        $snapshot = ContentArrays::stringMap($snapshot, 'catalog Content snapshot');
        $this->integrity->assertValid($snapshot);
        /** @var array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null} $snapshot */
        if (ContentOwnership::declared()) {
            if ($snapshot['ownerType'] !== $owner->getMorphClass() || $snapshot['ownerId'] !== $ownerId || $snapshot['group'] !== $group || $snapshot['formatVersion'] !== 3 || $snapshot['tenantId'] !== null || $snapshot['ownershipContext'] !== ['mode' => 'central', 'owner' => null]) {
                throw new OwnershipViolation('Catalog Content must be an exact native central snapshot.');
            }
        }
        $mediaIds = [];
        foreach ($snapshot['blocks'] as $block) {
            $schema = ContentSchema::fromArray($block['definitionSchema']);
            foreach ([$block['values'], $block['overrides'], ...array_values($block['translations'])] as $values) {
                foreach ($this->media->extract($schema, $values, null) as $reference) {
                    $mediaIds[$reference['id']] = true;
                }
            }
        }

        return ['ownerAlias' => $ownerAlias, 'ownerId' => $ownerId, 'group' => $group, 'grantId' => $grantId, 'sourceRevision' => $revision, 'sourceHash' => $snapshot['version'], 'snapshot' => $snapshot, 'mediaIds' => array_keys($mediaIds)];
    }

    /** @param array<string,string> $mediaMap
     * @param  array{ownerAlias:string,ownerId:string,group:string,grantId:string,sourceRevision:int,sourceHash:string,snapshot:array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null},mediaIds:list<string>}  $source
     * @return array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}
     */
    public function importSnapshot(Model $target, array $source, array $mediaMap, Authenticatable|SystemActor $actor): array
    {
        $native = ContentOwnership::snapshotContext();
        if ($native === null) {
            throw new OwnershipViolation('Content catalog import requires an admitted native owner.');
        }

        $target = $this->owners->canonical($target);
        if ($this->exportForCopy($source['ownerAlias'], $source['ownerId'], $source['group'], $source['grantId']) != $source) {
            throw new OwnershipViolation('Content catalog snapshot is stale or forged.');
        }
        $this->authorization->authorize(ContentAbility::Render, $actor, owner: $target, context: ['snapshot' => 'import']);
        foreach ($source['snapshot']['blocks'] as $block) {
            $this->importContentSnapshotAssertPortable((ContentSchema::fromArray($block['definitionSchema']))->fields);
        }

        $targetType = $this->owners->type($target);
        $targetId = $this->owners->id($target);
        $this->owners->assertGroup($target, $source['group']);
        foreach ($source['mediaIds'] as $sourceId) {
            $destinationId = $mediaMap[$sourceId] ?? throw new OwnershipViolation("Missing copied Media mapping for [{$sourceId}].");
            $this->boundary->query(MediaModelRegistry::query(), 'media.assets')->whereKey($destinationId)->firstOrFail();
        }
        $placementIds = [];
        $blockIds = [];
        foreach ($source['snapshot']['blocks'] as $block) {
            $placementIds[$block['placementId']] = (string) Str::uuid();
            $blockIds[$block['blockId']] = (string) Str::uuid();
        }
        $blocks = array_map(function (array $block) use ($placementIds, $blockIds, $mediaMap): array {
            $data = $block;
            foreach (['values', 'overrides'] as $key) {
                $data[$key] = ContentArrays::stringMap($this->importContentSnapshotReplaceMedia($block[$key], $mediaMap), 'copied Content values');
            }
            $data['translations'] = ContentArrays::translations($this->importContentSnapshotReplaceMedia($block['translations'], $mediaMap), 'copied Content translations');
            $data['placementId'] = $placementIds[$block['placementId']];
            $data['parentId'] = $block['parentId'] === null ? null : $placementIds[$block['parentId']] ?? throw new OwnershipViolation('Content snapshot parent is missing.');
            $data['blockId'] = $blockIds[$block['blockId']];

            return $data;
        }, $source['snapshot']['blocks']);

        return $this->integrity->rebind($source['snapshot'], $targetType, $targetId, $native, $blocks);
    }

    /** @param list<ContentFieldDefinition> $fields */
    private function importContentSnapshotAssertPortable(array $fields): void
    {
        foreach ($fields as $field) {
            if (in_array($field->type, ['reference', 'reference_list'], true)) {
                throw new OwnershipViolation('Content catalog references require explicit target mappings; this copy has none.');
            }
            $this->importContentSnapshotAssertPortable($field->fields);
            if ($field->item !== null) {
                $this->importContentSnapshotAssertPortable([$field->item]);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<string, string>  $mediaMap
     * @return array<array-key, mixed>
     */
    private function importContentSnapshotReplaceMedia(array $value, array $mediaMap): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->importContentSnapshotReplaceMedia($item, $mediaMap);
            } elseif (is_string($item)) {
                $value[$key] = $mediaMap[$item] ?? $item;
            }
        }

        return $value;
    }
}
