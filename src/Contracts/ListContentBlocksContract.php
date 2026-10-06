<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentBlockData;
use Nvl\Filterable\Data\FilterSet;

/**
 * Defines the supported list content blocks workflow.
 *
 * @api
 */
interface ListContentBlocksContract
{
    /**
     * @return LengthAwarePaginator<int, ContentBlockData>
     */
    public function execute(
        FilterSet $filterSet,
        ContentActorData $actor,
        int $perPage = 25,
    ): LengthAwarePaginator;
}
