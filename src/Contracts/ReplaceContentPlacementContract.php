<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentPlacementData;

/**
 * Defines the supported replace content placement workflow.
 *
 * @api
 */
interface ReplaceContentPlacementContract
{
    /**
     * Replace one exact owner-group placement block at its expected revision.
     */
    public function execute(
        Model&ContentOwner $owner,
        string $group,
        string $placement,
        string $block,
        int $expectedRevision,
        ContentActorData $actor,
    ): ContentPlacementData;
}
