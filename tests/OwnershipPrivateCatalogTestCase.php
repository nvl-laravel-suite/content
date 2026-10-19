<?php

declare(strict_types=1);

namespace Nvl\Content\Tests;

use Nvl\Content\Tests\Fixtures\OwnershipPrivateContentBlock;

/** Separates global code definitions from optional shared mutable content roots. */
abstract class OwnershipPrivateCatalogTestCase extends OwnershipTestCase
{
    protected function blockClass(): string
    {
        return OwnershipPrivateContentBlock::class;
    }
}
