<?php

declare(strict_types=1);

namespace Nvl\Content\Actions;

use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\GetContentBlockContract;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentBlockData;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Services\ContentModelRegistry;

/**
 * Reads one editable block through the package authorization boundary.
 *
 * @api
 */
final readonly class GetContentBlockAction implements GetContentBlockContract
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private ContentAuthorization $authorization) {}

    /** Resolve the exact admitted block and authorize its read. */
    public function execute(
        ContentBlock|string $block,
        ContentActorData $actor,
    ): ContentBlockData {
        $blockId = $block instanceof ContentBlock ? $block->id : $block;
        $model = ContentModelRegistry::query()
            ->with(['definition', 'translations'])
            ->findOrFail($blockId);
        $this->authorization->authorize(ContentAbility::View, $actor, $model);

        return ContentBlockData::fromModel($model);
    }
}
