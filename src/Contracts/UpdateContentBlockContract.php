<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\UpdateContentBlockData;
use Nvl\Content\Models\ContentBlock;

/**
 * Defines the supported update content block workflow.
 *
 * @api
 */
interface UpdateContentBlockContract
{
    /**
     * Execute the update content block workflow.
     */
    public function execute(
        ContentBlock|string $block,
        UpdateContentBlockData $data,
        ContentActorData $actor,
    ): ContentBlock;
}
