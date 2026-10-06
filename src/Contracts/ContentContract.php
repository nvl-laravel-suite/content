<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentBlockData;
use Nvl\Content\Data\ContentCompositionSnapshotData;
use Nvl\Content\Data\ContentDefinitionData;
use Nvl\Content\Data\ContentDefinitionMigrationPlanData;
use Nvl\Content\Data\ContentDefinitionMigrationResultData;
use Nvl\Content\Data\ContentDefinitionSyncPlanData;
use Nvl\Content\Data\ContentEditorData;
use Nvl\Content\Data\ContentFieldPresetData;
use Nvl\Content\Data\ContentPlacementData;
use Nvl\Content\Data\ContentScopeData;
use Nvl\Content\Data\ContentScopeResolutionData;
use Nvl\Content\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Data\Mutations\PlaceContentBlockData;
use Nvl\Content\Data\Mutations\UpdateContentBlockData;
use Nvl\Content\Data\Mutations\UpdateContentPlacementData;
use Nvl\Content\Data\RenderedContentCompositionData;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Filterable\Data\FilterSet;

/**
 * Defines the canonical model-first Content application surface.
 *
 * @api
 */
interface ContentContract
{
    /**
     * Return every active definition available to an authorized editor.
     *
     * @return Collection<int, ContentDefinitionData>
     */
    public function definitions(ContentActorData $actor): Collection;

    /**
     * Return every reusable semantic field preset available to an authorized editor.
     *
     * @return Collection<int, ContentFieldPresetData>
     */
    public function presets(ContentActorData $actor): Collection;

    /**
     * Return a filtered, authorized page of reusable Content blocks.
     *
     * @return LengthAwarePaginator<int, ContentBlockData>
     */
    public function blocks(
        FilterSet $filters,
        ContentActorData $actor,
        int $perPage = 25,
    ): LengthAwarePaginator;

    /**
     * Resolve complete localized values through ordered scope fallback.
     *
     * @param  list<ContentScopeData>  $scopes
     */
    public function resolveScopes(
        array $scopes,
        string $locale,
        ContentActorData $actor,
        ?int $limit = null,
        bool $publicOnly = true,
    ): ContentScopeResolutionData;

    /**
     * Return one authorized reusable Content block.
     */
    public function block(
        ContentBlock|string $block,
        ContentActorData $actor,
    ): ContentBlockData;

    /**
     * Plan or apply synchronization of source-controlled Content definitions.
     */
    public function syncDefinitions(
        ContentActorData $actor,
        bool $dryRun = false,
    ): ContentDefinitionSyncPlanData;

    /**
     * Build an exact, bounded, read-only definition migration plan.
     */
    public function planDefinitionMigrations(
        ContentActorData $actor,
        ?string $definition = null,
        ?int $limit = null,
    ): ContentDefinitionMigrationPlanData;

    /**
     * Atomically apply one exact definition migration plan.
     */
    public function applyDefinitionMigrations(
        ContentDefinitionMigrationPlanData $plan,
        ContentActorData $actor,
    ): ContentDefinitionMigrationResultData;

    /**
     * Create one reusable draft block through the canonical mutation boundary.
     */
    public function createBlock(
        CreateContentBlockData $data,
        ContentActorData $actor,
    ): ContentBlock;

    /**
     * Update one reusable block with optimistic concurrency.
     */
    public function updateBlock(
        ContentBlock|string $block,
        UpdateContentBlockData $data,
        ContentActorData $actor,
    ): ContentBlock;

    /**
     * Publish one exact reusable block revision.
     */
    public function publishBlock(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): ContentBlock;

    /**
     * Archive one exact reusable block revision.
     */
    public function archiveBlock(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): ContentBlock;

    /**
     * Soft-delete one exact unplaced block revision.
     */
    public function deleteBlock(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): void;

    /**
     * Restore one exact deleted block revision as a draft.
     */
    public function restoreBlock(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): ContentBlock;

    /**
     * Return every existing composition group on the owner.
     *
     * @return Collection<int, string>
     */
    public function groups(
        Model&ContentOwner $owner,
        ContentActorData $actor,
    ): Collection;

    /**
     * Return every editable placement in one owner composition group.
     *
     * @return Collection<int, ContentPlacementData>
     */
    public function placements(
        Model&ContentOwner $owner,
        string $group,
        ContentActorData $actor,
    ): Collection;

    /**
     * Return the complete typed bootstrap payload for a consumer-owned editor.
     */
    public function editor(
        Model&ContentOwner $owner,
        string $group,
        ContentActorData $actor,
    ): ContentEditorData;

    /**
     * Place one reusable block in an owner composition group.
     */
    public function place(
        ContentBlock|string $block,
        Model&ContentOwner $owner,
        string $group,
        PlaceContentBlockData $data,
        ContentActorData $actor,
    ): ContentPlacement;

    /**
     * Update one revision-safe placement.
     */
    public function updatePlacement(
        ContentPlacement|string $placement,
        UpdateContentPlacementData $data,
        ContentActorData $actor,
    ): ContentPlacement;

    /**
     * Remove one revision-safe leaf placement.
     */
    public function deletePlacement(
        ContentPlacement|string $placement,
        int $expectedRevision,
        ContentActorData $actor,
    ): void;

    /**
     * Render one live owner composition group.
     */
    public function render(
        Model&ContentOwner $owner,
        string $group,
        string $locale,
        ContentActorData $actor,
        bool $publicOnly = true,
    ): RenderedContentCompositionData;

    /**
     * Capture one owner composition group as an immutable snapshot.
     */
    public function capture(
        Model&ContentOwner $owner,
        string $group,
        ContentActorData $actor,
        bool $publishing = false,
    ): ContentCompositionSnapshotData;

    /**
     * Render one verified immutable composition snapshot.
     */
    public function renderSnapshot(
        ContentCompositionSnapshotData $snapshot,
        string $locale,
        ContentActorData $actor,
    ): RenderedContentCompositionData;

    /** Adopt one hash-valid format-1 snapshot under its canonical active tenant owner. */
    public function adoptSnapshot(ContentCompositionSnapshotData $snapshot): ContentCompositionSnapshotData;
}
