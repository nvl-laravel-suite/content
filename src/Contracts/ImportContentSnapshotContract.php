<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentCompositionSnapshotData;
use Nvl\Content\Data\ContentSnapshotCopyData;

/**
 * Defines the supported import content snapshot workflow.
 *
 * @api
 */
interface ImportContentSnapshotContract
{
    /** @param array<string,string> $mediaMap */
    public function execute(
        Model&ContentOwner $target,
        ContentSnapshotCopyData $source,
        array $mediaMap,
        ContentActorData $actor,
    ): ContentCompositionSnapshotData;
}
