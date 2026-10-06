<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentBlockData;
use Nvl\Content\Models\ContentBlock;

/**
 * Defines the supported get content block workflow.
 *
 * @api
 */
interface GetContentBlockContract
{
    /**
     * Execute the get content block workflow.
     */
    public function execute(
        ContentBlock|string $block,
        ContentActorData $actor,
    ): ContentBlockData;
}
