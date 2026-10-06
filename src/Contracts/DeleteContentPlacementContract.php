<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Models\ContentPlacement;

/**
 * Defines the supported delete content placement workflow.
 *
 * @api
 */
interface DeleteContentPlacementContract
{
    /**
     * Remove one leaf placement at the exact expected revision.
     */
    public function execute(
        ContentPlacement|string $placement,
        int $expectedRevision,
        ContentActorData $actor,
    ): void;
}
