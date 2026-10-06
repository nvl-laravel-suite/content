<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nvl\Content\Data\ContentActorData;

/**
 * Defines the supported list content groups workflow.
 *
 * @api
 */
interface ListContentGroupsContract
{
    /**
     * Return the owner’s existing group keys in deterministic order.
     *
     * @return Collection<int, string>
     */
    public function execute(
        Model&ContentOwner $owner,
        ContentActorData $actor,
    ): Collection;
}
