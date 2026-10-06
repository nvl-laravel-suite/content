<?php

declare(strict_types=1);

namespace Nvl\Content\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\DeleteContentBlockContract;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentRevisionEvent;
use Nvl\Content\Events\ContentBlockChanged;
use Nvl\Content\Exceptions\StaleContentException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Services\ContentMediaSynchronizer;
use Nvl\Content\Services\ContentRevisionRecorder;
use Nvl\Support\Events\DomainEventDispatcher;

/**
 * Soft-deletes content after detaching references; Media binaries remain intact.
 *
 * @api
 */
final readonly class DeleteContentBlockAction implements DeleteContentBlockContract
{
    public function __construct(
        private ContentAuthorization $authorization,
        private ContentMediaSynchronizer $media,
        private ContentRevisionRecorder $revisions,
        private DomainEventDispatcher $domainEvents,
    ) {}

    public function execute(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): void {
        $blockId = $block instanceof ContentBlock ? $block->id : $block;

        DB::connection((new ContentBlock)->getConnectionName())
            ->transaction(function () use ($actor, $blockId, $expectedRevision): void {
                $model = ContentBlock::query()->lockForUpdate()->findOrFail($blockId);
                $this->authorization->authorize(ContentAbility::Delete, $actor, $model);

                if ($model->revision !== $expectedRevision) {
                    throw StaleContentException::forRevision(
                        $model->id,
                        $expectedRevision,
                        $model->revision,
                    );
                }

                if ($model->placements()->lockForUpdate()->first(['id']) !== null) {
                    throw new InvalidArgumentException(
                        'A placed content block cannot be deleted; remove every placement first.',
                    );
                }

                $model->forceFill(['revision' => $model->revision + 1])->save();
                $this->revisions->record($model, ContentRevisionEvent::Deleted, $actor);
                $this->media->detachAll($model);
                $model->delete();
                $this->domainEvents->dispatch(new ContentBlockChanged(
                    $model->id,
                    ContentRevisionEvent::Deleted,
                    $model->revision,
                    $actor,
                ), $model->getConnection());
            });
    }
}
