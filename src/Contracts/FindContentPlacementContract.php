<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentPlacementData;

/**
 * Defines the supported find content placement workflow.
 *
 * @api
 */
interface FindContentPlacementContract
{
    /**
     * Return one editable placement by exact ID or key inside its owner group.
     */
    public function execute(
        Model&ContentOwner $owner,
        string $group,
        string $idOrKey,
        ContentActorData $actor,
    ): ContentPlacementData;
}
