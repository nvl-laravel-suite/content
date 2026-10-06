<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentPlacementData;

/**
 * Defines the supported list owner content placement summaries workflow.
 *
 * @api
 */
interface ListOwnerContentPlacementSummariesContract
{
    /**
     * Return deterministic placement summaries keyed by canonical owner identity.
     *
     * @param  iterable<array-key, Model&ContentOwner>  $owners
     * @return array<string, list<ContentPlacementData>>
     */
    public function execute(
        iterable $owners,
        string $group,
        ContentActorData $actor,
    ): array;
}
