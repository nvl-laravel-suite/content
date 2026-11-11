<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentPlacementEvent;
use Nvl\Content\Events\ContentPlacementChanged;
use Nvl\Content\Exceptions\StaleContentException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Validation\ContentObjectRule;
use Nvl\Support\Auth\CanonicalActorResolver;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Events\DomainEventDispatcher;

/**
 * Native Content workflow family.
 *
 * @api
 */
final class ContentPlacements
{
    public function __construct(private CanonicalActorResolver $actors, private ContentAuthorization $authorization, private ContentOwnerRegistry $owners, private ContentPlacementValidator $validator, private ContentIdentityGuard $identities, private ContentPlacementOwnerLock $ownerLocks, private ContentMediaSynchronizer $media, private DomainEventDispatcher $domainEvents, private ContentPlacementTree $tree) {}

    /**
     * Place one block in a validated, revisioned owner group tree.
     *
     * @param  array{key:string,region?:string,parentId?:string|null,sortOrder?:int,isVisible?:bool,overrides?:array<string, mixed>}  $data
     */
    public function place(ContentBlock|string $block, Model $owner, string $group, array $data, Authenticatable|SystemActor $actor): ContentPlacement
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        Validator::make($data, self::placeRules())->validate();
        $data += ['region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []];
        $model = $block instanceof ContentBlock ? $block->loadMissing(['definition', 'translations']) : ContentModelRegistry::query()->with(['definition', 'translations'])->findOrFail($this->identities->uuid($block, ContentBlock::class));
        $owner = $this->owners->canonical($owner);
        $ownerType = $this->owners->type($owner);
        $ownerId = $this->owners->id($owner);
        $this->owners->assertGroup($owner, $group);
        $this->identities->placementKey($data['key']);
        $this->identities->region($data['region']);
        $this->identities->sortOrder($data['sortOrder']);

        return $this->ownerLocks->run($ownerType, $ownerId, $group, fn (): ContentPlacement => DB::connection($model->getConnectionName())->transaction(function () use ($actor, $data, $group, $model, $owner, $ownerId, $ownerType): ContentPlacement {
            $this->owners->id($owner);
            $lockedBlock = ContentModelRegistry::query()->with(['definition', 'translations'])->lockForUpdate()->findOrFail($model->id);
            $this->authorization->authorize(ContentAbility::Place, $actor, $lockedBlock, $owner, ['group' => $group, 'region' => $data['region'], 'parent_id' => $data['parentId']]);
            $maximum = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);
            $locked = ContentPlacement::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('group', $group)->limit($maximum + 1)->lockForUpdate()->get(['id']);
            if ($locked->count() >= $maximum) {
                throw new InvalidArgumentException("Content owner already has the maximum {$maximum} placements.");
            }
            if (ContentPlacement::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('group', $group)->where('key', $data['key'])->exists()) {
                throw new InvalidArgumentException("Content placement key [{$data['key']}] already exists in this owner group.");
            }
            $overrides = $this->validator->validate($lockedBlock, $owner, $ownerType, $ownerId, $group, $data['region'], $data['parentId'], $data['overrides'], $actor);
            $placement = ContentPlacement::query()->create(['content_block_id' => $lockedBlock->id, 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group, 'key' => $data['key'], 'parent_id' => $data['parentId'], 'region' => $data['region'], 'sort_order' => $data['sortOrder'], 'is_visible' => $data['isVisible'], 'overrides' => $overrides === [] ? null : $overrides]);
            $this->media->synchronizePlacement($placement, $lockedBlock->definition_schema, $overrides, $actor, $owner);
            $this->domainEvents->dispatch(new ContentPlacementChanged($placement->id, ContentPlacementEvent::Created, $placement->revision, $actor, $ownerType, $ownerId, $group, $lockedBlock->id), $placement->getConnection());

            return $placement;
        }, 3));
    }

    /**
     * Update one placement, preserving omitted parent and override values.
     *
     * @param  array{expected_revision:int,region:string,parentId?:string|null,sortOrder:int,isVisible:bool,overrides?:array<string, mixed>}  $data
     */
    public function update(ContentPlacement|string $placement, array $data, Authenticatable|SystemActor $actor): ContentPlacement
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        Validator::make($data, self::updateRules())->validate();
        $placementId = $placement instanceof ContentPlacement ? $placement->id : $this->identities->uuid($placement, ContentPlacement::class);
        $this->identities->region($data['region']);
        $this->identities->sortOrder($data['sortOrder']);
        $identity = ContentPlacement::query()->findOrFail($placementId, ['owner_type', 'owner_id', 'group']);

        return $this->ownerLocks->run($identity->owner_type, $identity->owner_id, $identity->group, fn (): ContentPlacement => DB::connection((new ContentPlacement)->getConnectionName())->transaction(function () use ($actor, $data, $placementId): ContentPlacement {
            $model = ContentPlacement::query()->lockForUpdate()->findOrFail($placementId);
            $data += ['parentId' => $model->parent_id, 'overrides' => $model->overrides ?? []];
            $block = ContentModelRegistry::query()->with(['definition', 'translations'])->lockForUpdate()->findOrFail($model->content_block_id);
            $model->setRelation('block', $block);
            $owner = $this->owners->resolve($model->owner_type, $model->owner_id);
            $this->owners->assertGroup($owner, $model->group);
            $this->authorization->authorize(ContentAbility::Place, $actor, $model->block, $owner, ['group' => $model->group, 'region' => $data['region'], 'parent_id' => $data['parentId']]);
            if ($model->revision !== $data['expected_revision']) {
                throw StaleContentException::forRevision($model->id, $data['expected_revision'], $model->revision);
            }
            ContentPlacement::query()->where('owner_type', $model->owner_type)->where('owner_id', $model->owner_id)->where('group', $model->group)->lockForUpdate()->get(['id']);
            $overrides = $this->validator->validate($model->block, $owner, $model->owner_type, $model->owner_id, $model->group, $data['region'], $data['parentId'], $data['overrides'], $actor, $model->id);
            $model->forceFill(['parent_id' => $data['parentId'], 'region' => $data['region'], 'sort_order' => $data['sortOrder'], 'is_visible' => $data['isVisible'], 'overrides' => $overrides === [] ? null : $overrides, 'revision' => $model->revision + 1])->save();
            $this->media->synchronizePlacement($model, $block->definition_schema, $overrides, $actor, $owner);
            $this->domainEvents->dispatch(new ContentPlacementChanged($model->id, ContentPlacementEvent::Updated, $model->revision, $actor, $model->owner_type, $model->owner_id, $model->group, $model->content_block_id), $model->getConnection());

            return $model->refresh();
        }, 3));
    }

    /**
     * Apply one complete revision-safe tree proposal and return the fresh editor.
     *
     * @param  array{placements:list<array{id:string,expected_revision:int,region:string,parentId?:string|null,sortOrder:int}>}  $data
     * @return array<int,ContentPlacement>
     */
    public function move(Model $owner, string $group, array $data, Authenticatable|SystemActor $actor): array
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        Validator::make($data, self::moveRules())->validate();
        $maximum = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);
        $proposal = $this->reorderContentPlacementsNormalizeProposal($data, $maximum);
        $owner = $this->owners->canonical($owner);
        $ownerType = $this->owners->type($owner);
        $ownerId = $this->owners->id($owner);
        $this->owners->assertGroup($owner, $group);

        return $this->ownerLocks->run($ownerType, $ownerId, $group, fn (): array => DB::connection((new ContentPlacement)->getConnectionName())->transaction(function () use ($actor, $group, $maximum, $owner, $ownerId, $ownerType, $proposal): array {
            $this->owners->id($owner);
            /** @var Collection<int, ContentPlacement> $placements */
            $placements = ContentPlacement::query()->with(['block.definition', 'block.translations'])->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('group', $group)->orderBy('id')->limit($maximum + 1)->lockForUpdate()->get();
            if ($placements->count() > $maximum) {
                throw new InvalidArgumentException("Content owner exceeds the {$maximum} placement limit.");
            }
            $placementIds = $placements->pluck('id')->all();
            $proposalIds = array_keys($proposal);
            sort($proposalIds);
            if ($placementIds !== $proposalIds) {
                throw new InvalidArgumentException('A Content reorder must contain exactly one item for every owner-group placement.');
            }
            foreach ($placements as $placement) {
                $item = $proposal[$placement->id];
                $block = $placement->getRelation('block');
                if (! $block instanceof ContentBlock) {
                    throw new InvalidArgumentException("Content placement [{$placement->id}] references a missing block.");
                }
                $this->authorization->authorize(ContentAbility::Place, $actor, $block, $owner, ['group' => $group, 'region' => $item['region'], 'parent_id' => $item['parentId'], 'reorders_placements' => true]);
            }
            foreach ($placements as $placement) {
                $expected = $proposal[$placement->id]['expected_revision'];
                if ($placement->revision !== $expected) {
                    throw StaleContentException::forRevision($placement->id, $expected, $placement->revision);
                }
            }
            foreach ($placements as $placement) {
                $item = $proposal[$placement->id];
                $block = $placement->getRelation('block');
                if (! $block instanceof ContentBlock) {
                    throw new InvalidArgumentException("Content placement [{$placement->id}] references a missing block.");
                }
                $this->validator->validateDefinition($block, $owner, $group, $item['region'], is_array($placement->overrides) ? $placement->overrides : [], $actor);
            }
            foreach ($placements as $placement) {
                $item = $proposal[$placement->id];
                $placement->forceFill(['region' => $item['region'], 'parent_id' => $item['parentId'], 'sort_order' => $item['sortOrder']]);
            }
            $this->tree->assertValidProposal($placements, $ownerType, $ownerId, $group);
            $changed = $placements->filter(static fn (ContentPlacement $placement): bool => $placement->isDirty(['region', 'parent_id', 'sort_order']));
            foreach ($changed as $placement) {
                $placement->forceFill(['revision' => $placement->revision + 1])->save();
            }
            foreach ($changed as $placement) {
                $this->domainEvents->dispatch(new ContentPlacementChanged($placement->id, ContentPlacementEvent::Updated, $placement->revision, $actor, $ownerType, $ownerId, $group, $placement->content_block_id), $placement->getConnection());
            }

            return app(ContentReader::class)->placements($owner, $group, $actor)->values()->all();
        }, 3));
    }

    /**
     * @param  array{placements:list<array{id:string,expected_revision:int,region:string,parentId?:string|null,sortOrder:int}>}  $data
     * @return array<string, array{id:string,expected_revision:int,region:string,parentId:string|null,sortOrder:int}>
     */
    private function reorderContentPlacementsNormalizeProposal(array $data, int $maximum): array
    {
        if (count($data['placements']) > $maximum) {
            throw new InvalidArgumentException("A Content reorder supports at most {$maximum} placements.");
        }
        $proposal = [];
        foreach ($data['placements'] as $item) {
            $item += ['parentId' => null];
            if (! Str::isUuid($item['id']) || $item['parentId'] !== null && ! Str::isUuid($item['parentId'])) {
                throw new InvalidArgumentException('Content reorder placement and parent identifiers must be UUIDs.');
            }
            $this->identities->region($item['region']);
            $this->identities->sortOrder($item['sortOrder']);
            if (isset($proposal[$item['id']])) {
                throw new InvalidArgumentException("Content reorder contains duplicate placement [{$item['id']}].");
            }
            $proposal[$item['id']] = $item;
        }
        ksort($proposal);

        return $proposal;
    }

    /**
     * Replace one exact owner-group placement block at its expected revision.
     */
    public function replace(Model $owner, string $group, string $placement, string $block, int $expectedRevision, Authenticatable|SystemActor $actor): ContentPlacement
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        if (! Str::isUuid($placement) || ! Str::isUuid($block)) {
            throw new InvalidArgumentException('Content placement and replacement block identifiers must be UUIDs.');
        }
        $owner = $this->owners->canonical($owner);
        $ownerType = $this->owners->type($owner);
        $ownerId = $this->owners->id($owner);
        $this->owners->assertGroup($owner, $group);

        return $this->ownerLocks->run($ownerType, $ownerId, $group, fn (): ContentPlacement => DB::connection((new ContentPlacement)->getConnectionName())->transaction(function () use ($actor, $block, $expectedRevision, $group, $owner, $ownerId, $ownerType, $placement): ContentPlacement {
            $this->owners->id($owner);
            $maximum = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);
            $placements = ContentPlacement::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('group', $group)->orderBy('id')->limit($maximum + 1)->lockForUpdate()->get();
            if ($placements->count() > $maximum) {
                throw new InvalidArgumentException("Content owner exceeds the {$maximum} placement limit.");
            }
            $model = $placements->firstWhere('id', $placement);
            if (! $model instanceof ContentPlacement) {
                throw (new ModelNotFoundException)->setModel(ContentPlacement::class, [$placement]);
            }
            $replacement = ContentModelRegistry::query()->with(['definition', 'translations'])->lockForUpdate()->findOrFail($block);
            $this->authorization->authorize(ContentAbility::Place, $actor, $replacement, $owner, ['group' => $group, 'region' => $model->region, 'parent_id' => $model->parent_id, 'replaces_placement' => true]);
            if ($model->revision !== $expectedRevision) {
                throw StaleContentException::forRevision($model->id, $expectedRevision, $model->revision);
            }
            $overrides = $this->validator->validate($replacement, $owner, $ownerType, $ownerId, $group, $model->region, $model->parent_id, is_array($model->overrides) ? $model->overrides : [], $actor, $model->id);
            $model->forceFill(['content_block_id' => $replacement->id, 'overrides' => $overrides === [] ? null : $overrides, 'revision' => $model->revision + 1])->save();
            $this->media->synchronizePlacement($model, $replacement->definition_schema, $overrides, $actor, $owner);
            $this->domainEvents->dispatch(new ContentPlacementChanged($model->id, ContentPlacementEvent::Updated, $model->revision, $actor, $ownerType, $ownerId, $group, $replacement->id), $model->getConnection());

            return $model->refresh()->load(['block.definition', 'block.translations']);
        }, 3));
    }

    /**
     * Remove one leaf placement at the exact expected revision.
     */
    public function delete(ContentPlacement|string $placement, int $expectedRevision, Authenticatable|SystemActor $actor): void
    {
        if ($actor instanceof Authenticatable) {
            $actor = $this->actors->resolve($actor);
        }
        $placementId = $placement instanceof ContentPlacement ? $placement->id : $this->identities->uuid($placement, ContentPlacement::class);
        $identity = ContentPlacement::query()->findOrFail($placementId, ['owner_type', 'owner_id', 'group']);
        $this->ownerLocks->run($identity->owner_type, $identity->owner_id, $identity->group, function () use ($actor, $expectedRevision, $placementId): void {
            DB::connection((new ContentPlacement)->getConnectionName())->transaction(function () use ($actor, $expectedRevision, $placementId): void {
                $model = ContentPlacement::query()->with('block')->lockForUpdate()->findOrFail($placementId);
                $owner = $this->owners->resolve($model->owner_type, $model->owner_id);
                $this->owners->assertGroup($owner, $model->group);
                $this->authorization->authorize(ContentAbility::Unplace, $actor, $model->block, $owner, ['group' => $model->group]);
                if ($model->revision !== $expectedRevision) {
                    throw StaleContentException::forRevision($model->id, $expectedRevision, $model->revision);
                }
                if (ContentPlacement::query()->where('parent_id', $model->id)->lockForUpdate()->first(['id']) !== null) {
                    throw new InvalidArgumentException('A content placement with children cannot be removed; move or remove its children first.');
                }
                $nextRevision = $model->revision + 1;
                $this->media->detachAll($model, $actor);
                if ($model->delete() !== true) {
                    throw new InvalidArgumentException('Content placement deletion was canceled.');
                }
                $this->domainEvents->dispatch(new ContentPlacementChanged($model->id, ContentPlacementEvent::Deleted, $nextRevision, $actor, $model->owner_type, $model->owner_id, $model->group, $model->content_block_id), $model->getConnection());
            }, 3);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function placeRules(): array
    {
        return ['key' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/D'], 'region' => ['sometimes', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/D'], 'parentId' => ['nullable', 'uuid'], 'sortOrder' => ['sometimes', 'integer:strict', 'min:0'], 'isVisible' => ['sometimes', 'boolean'], 'overrides' => ['array', new ContentObjectRule]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function updateRules(): array
    {
        return ['expected_revision' => ['required', 'integer:strict', 'min:1'], 'region' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/D'], 'parentId' => ['nullable', 'uuid'], 'sortOrder' => ['required', 'integer:strict', 'min:0'], 'isVisible' => ['required', 'boolean'], 'overrides' => ['array', new ContentObjectRule]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function moveRules(): array
    {
        $maximum = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);

        return ['placements' => ['present', 'array', "max:{$maximum}"], 'placements.*' => ['array'], 'placements.*.id' => ['required', 'uuid'], 'placements.*.expected_revision' => ['required', 'integer:strict', 'min:1'], 'placements.*.region' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/D'], 'placements.*.parentId' => ['nullable', 'uuid'], 'placements.*.sortOrder' => ['required', 'integer:strict', 'min:0']];
    }
}
