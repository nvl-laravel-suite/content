<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Models\ContentBlock;

/**
 * Defines the supported delete content block workflow.
 *
 * @api
 */
interface DeleteContentBlockContract
{
    /**
     * Execute the delete content block workflow.
     */
    public function execute(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): void;
}
