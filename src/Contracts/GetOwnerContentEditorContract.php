<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentEditorData;

/**
 * Defines the supported get owner content editor workflow.
 *
 * @api
 */
interface GetOwnerContentEditorContract
{
    /**
     * Return the complete typed bootstrap payload for one consumer-owned editor.
     */
    public function execute(
        Model&ContentOwner $owner,
        string $group,
        ContentActorData $actor,
    ): ContentEditorData;
}
