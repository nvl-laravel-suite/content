<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentBlockData;

/**
 * Defines the supported find content block by key workflow.
 *
 * @api
 */
interface FindContentBlockByKeyContract
{
    /**
     * Return one authorized editable block with an exact unambiguous key.
     */
    public function execute(string $key, ContentActorData $actor): ContentBlockData;
}
