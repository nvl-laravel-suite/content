<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentPlacementData;

/**
 * Defines the supported list content placements workflow.
 *
 * @api
 */
interface ListContentPlacementsContract
{
    /**
     * Return every placement fact in one authorized owner group.
     *
     * @return Collection<int, ContentPlacementData>
     */
    public function execute(
        Model&ContentOwner $owner,
        string $group,
        ContentActorData $actor,
        bool $includeBlocks = false,
    ): Collection;
}
