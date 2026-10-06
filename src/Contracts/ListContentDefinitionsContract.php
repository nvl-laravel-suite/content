<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Support\Collection;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentDefinitionData;

/**
 * Defines the supported list content definitions workflow.
 *
 * @api
 */
interface ListContentDefinitionsContract
{
    /**
     * @return Collection<int, ContentDefinitionData>
     */
    public function execute(ContentActorData $actor): Collection;
}
