<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentSnapshotCopyData;

/**
 * Defines the supported export content snapshot for copy workflow.
 *
 * @api
 */
interface ExportContentSnapshotForCopyContract
{
    /**
     * Execute the export content snapshot for copy workflow.
     */
    public function execute(string $ownerAlias, string $ownerId, string $group, string $grantId): ContentSnapshotCopyData;
}
