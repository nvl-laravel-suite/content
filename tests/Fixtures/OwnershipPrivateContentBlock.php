<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

/** An owned Content library that never shares mutable roots through a central catalog. */
final class OwnershipPrivateContentBlock extends OwnershipContentBlock
{
    public static function allowsCentralCatalog(): bool
    {
        return false;
    }
}
