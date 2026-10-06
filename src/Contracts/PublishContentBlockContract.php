<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Models\ContentBlock;

/**
 * Defines the supported publish content block workflow.
 *
 * @api
 */
interface PublishContentBlockContract
{
    /**
     * Execute the publish content block workflow.
     */
    public function execute(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): ContentBlock;
}
