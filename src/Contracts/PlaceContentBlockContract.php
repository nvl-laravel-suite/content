<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\PlaceContentBlockData;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;

/**
 * Defines the supported place content block workflow.
 *
 * @api
 */
interface PlaceContentBlockContract
{
    /**
     * Place one block in a validated, revisioned owner group tree.
     */
    public function execute(
        ContentBlock|string $block,
        Model&ContentOwner $owner,
        string $group,
        PlaceContentBlockData $data,
        ContentActorData $actor,
    ): ContentPlacement;
}
