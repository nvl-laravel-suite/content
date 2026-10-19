<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Nvl\Content\Models\ContentBlock;

/**
 * Canonical storage port for package integration and controlled source adoption.
 *
 * @api
 */
interface ContentStorage
{
    /** Return the actual source of the configured Content root. */
    public function connection(): Connection;

    /** Determine whether the configured Content root declares primary ownership. */
    public function owned(): bool;

    /** @return Builder<ContentBlock> */
    public function blocks(): Builder;
}
