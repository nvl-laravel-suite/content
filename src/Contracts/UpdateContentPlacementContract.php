<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\UpdateContentPlacementData;
use Nvl\Content\Models\ContentPlacement;

/**
 * Defines the supported update content placement workflow.
 *
 * @api
 */
interface UpdateContentPlacementContract
{
    /**
     * Update one placement while preserving its owner and group identity.
     */
    public function execute(
        ContentPlacement|string $placement,
        UpdateContentPlacementData $data,
        ContentActorData $actor,
    ): ContentPlacement;
}
