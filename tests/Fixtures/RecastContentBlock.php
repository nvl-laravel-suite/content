<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Nvl\Content\Models\ContentBlock;

/** Unsupported consumer root that replaces a required package cast. */
final class RecastContentBlock extends ContentBlock
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'metadata' => 'json'];
    }
}
