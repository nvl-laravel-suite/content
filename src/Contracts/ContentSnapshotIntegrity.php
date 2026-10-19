<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Data\ContentCompositionSnapshotBlockData;
use Nvl\Content\Data\ContentCompositionSnapshotData;

/**
 * Pure snapshot hashing, with authorization owned by the calling workflow.
 *
 * @api
 */
interface ContentSnapshotIntegrity
{
    /** Reject a snapshot whose canonical checksum has changed. */
    public function assertValid(ContentCompositionSnapshotData $snapshot): void;

    /** Compute the canonical content and ownership checksum. */
    public function digest(ContentCompositionSnapshotData $snapshot): string;

    /** @param array<string,mixed> $context
     * @param  list<ContentCompositionSnapshotBlockData>|null  $blocks
     */
    public function rebind(ContentCompositionSnapshotData $source, string $type, string $id, array $context, ?array $blocks = null): ContentCompositionSnapshotData;
}
