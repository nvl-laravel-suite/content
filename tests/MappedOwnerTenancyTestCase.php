<?php

declare(strict_types=1);

namespace Nvl\Content\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Tests\Fixtures\TenantContentOwner;

/** Exercises class-list capabilities against an explicitly host-owned morph map. */
abstract class MappedOwnerTenancyTestCase extends TenancyTestCase
{
    /** @var array<string, class-string<Model>> */
    private array $originalMorphMap = [];

    /** Configure the host identity before package providers inspect capabilities. */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->originalMorphMap = Relation::morphMap();
        Relation::morphMap(['host.content-owner' => TenantContentOwner::class]);
        $app['config']->set('nvl-core.owners', [TenantContentOwner::class]);
        $app['config']->set('nvl-content.owners', [TenantContentOwner::class]);
    }

    /** Preserve the external host map after every fixture, including boot failures. */
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            Relation::morphMap($this->originalMorphMap, false);
        }
    }
}
