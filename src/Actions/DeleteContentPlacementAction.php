<?php

declare(strict_types=1);

namespace Nvl\Content\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\DeleteContentPlacementContract;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentPlacementEvent;
use Nvl\Content\Events\ContentPlacementChanged;
use Nvl\Content\Exceptions\StaleContentException;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Services\ContentMediaSynchronizer;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentPlacementOwnerLock;
use Nvl\Support\Events\DomainEventDispatcher;

/**
 * Removes a leaf placement without deleting its reusable block.
 *
 * @api
 */
final readonly class DeleteContentPlacementAction implements DeleteContentPlacementContract
{
    public function __construct(
        private ContentAuthorization $authorization,
        private ContentOwnerRegistry $owners,
        private ContentPlacementOwnerLock $ownerLocks,
        private ContentMediaSynchronizer $media,
        private DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * Remove one leaf placement at the exact expected revision.
     */
    public function execute(
        ContentPlacement|string $placement,
        int $expectedRevision,
        ContentActorData $actor,
    ): void {
        $placementId = $placement instanceof ContentPlacement ? $placement->id : $placement;
        $identity = ContentPlacement::query()
            ->findOrFail($placementId, ['owner_type', 'owner_id', 'group']);

        $this->ownerLocks->run(
            $identity->owner_type,
            $identity->owner_id,
            $identity->group,
            function () use ($actor, $expectedRevision, $placementId): void {
                DB::connection((new ContentPlacement)->getConnectionName())
                    ->transaction(function () use ($actor, $expectedRevision, $placementId): void {
                        $model = ContentPlacement::query()
                            ->with('block')
                            ->lockForUpdate()
                            ->findOrFail($placementId);
                        $owner = $this->owners->resolve($model->owner_type, $model->owner_id);
                        $this->owners->assertGroup($owner, $model->group);
                        $this->authorization->authorize(
                            ContentAbility::Unplace,
                            $actor,
                            $model->block,
                            $owner,
                            ['group' => $model->group],
                        );

                        if ($model->revision !== $expectedRevision) {
                            throw StaleContentException::forRevision(
                                $model->id,
                                $expectedRevision,
                                $model->revision,
                            );
                        }

                        if (ContentPlacement::query()
                            ->where('parent_id', $model->id)
                            ->lockForUpdate()
                            ->first(['id']) !== null) {
                            throw new InvalidArgumentException(
                                'A content placement with children cannot be removed; move or remove its children first.',
                            );
                        }

                        $nextRevision = $model->revision + 1;
                        $this->media->detachAll($model);

                        if ($model->delete() !== true) {
                            throw new InvalidArgumentException('Content placement deletion was canceled.');
                        }

                        $this->domainEvents->dispatch(new ContentPlacementChanged(
                            $model->id,
                            ContentPlacementEvent::Deleted,
                            $nextRevision,
                            $actor,
                            $model->owner_type,
                            $model->owner_id,
                            $model->group,
                            $model->content_block_id,
                        ), $model->getConnection());
                    }, 3);
            },
        );
    }
}
