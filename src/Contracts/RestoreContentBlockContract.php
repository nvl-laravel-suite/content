<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Models\ContentBlock;

/**
 * Defines the supported restore content block workflow.
 *
 * @api
 */
interface RestoreContentBlockContract
{
    /**
     * Execute the restore content block workflow.
     */
    public function execute(
        ContentBlock|string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): ContentBlock;
}
