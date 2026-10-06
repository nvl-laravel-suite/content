<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Models\ContentBlock;

/**
 * Defines the supported create content block workflow.
 *
 * @api
 */
interface CreateContentBlockContract
{
    /**
     * Execute the create content block workflow.
     */
    public function execute(
        CreateContentBlockData $data,
        ContentActorData $actor,
    ): ContentBlock;
}
