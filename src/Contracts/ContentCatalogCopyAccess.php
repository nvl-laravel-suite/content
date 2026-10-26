<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Revalidates the current recipient and exact native central source grant. */
interface ContentCatalogCopyAccess
{
    public function canonicalSource(string $ownerId, string $group, string $grantId, bool $lock = false): Model;
}
