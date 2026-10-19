<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

/** Unsupported consumer query override that strips native ownership. */
final class UnsafeQueryContentBlock extends OwnershipContentBlock
{
    /** @return Builder<static> */
    public function newQuery(): Builder
    {
        return $this->newModelQuery()->withoutGlobalScopes();
    }
}
