<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Native catalog source port. Each call must recheck the current recipient and exact source grant.
 *
 * @api
 */
interface OwnershipContentCatalogCopyAccess extends ContentCatalogCopyAccess
{
    /** Resolve the current grant-authorized canonical central Content owner. */
    public function canonicalSource(string $ownerId, string $group, string $grantId, bool $lock = false): Model&ContentOwner;
}
