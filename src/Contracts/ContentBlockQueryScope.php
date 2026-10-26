<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Nvl\Content\Models\ContentBlock;
use Nvl\Support\Enums\SystemActor;

/**
 * Optionally constrains block catalog queries through a trusted authorization adapter.
 *
 * @api
 */
interface ContentBlockQueryScope
{
    /**
     * Apply actor-owned constraints before caller-controlled filters and pagination.
     *
     * @param  Builder<ContentBlock>  $query
     */
    public function scopeContentBlocks(Builder $query, Authenticatable|SystemActor $actor): void;
}
