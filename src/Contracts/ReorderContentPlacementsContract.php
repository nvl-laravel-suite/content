<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentEditorData;
use Nvl\Content\Data\Mutations\ReorderContentPlacementsData;

/**
 * Defines the supported reorder content placements workflow.
 *
 * @api
 */
interface ReorderContentPlacementsContract
{
    /**
     * Apply one complete revision-safe tree proposal and return the fresh editor.
     */
    public function execute(
        Model&ContentOwner $owner,
        string $group,
        ReorderContentPlacementsData $data,
        ContentActorData $actor,
    ): ContentEditorData;
}
