<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Nvl\Content\Tests\OwnershipTestCase;

/** Exercises repeated preparation on one physically prefixed source. */
class ContentPrefixedOwnershipTestCase extends OwnershipTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.connections.sqlite.prefix', 'review_');
    }
}
