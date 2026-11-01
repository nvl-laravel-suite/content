<?php

declare(strict_types=1);

namespace Nvl\Content\Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Content\Tests\Fixtures\StandaloneContentAuthorization;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase;

/** Exercises Content with its required providers and no optional Media integration. */
abstract class StandaloneContentTestCase extends TestCase
{
    use DatabaseMigrations;

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [LocaleServiceProvider::class, TranslatableServiceProvider::class, ContentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->bind(ContentAuthorization::class, StandaloneContentAuthorization::class);
        $app['config']->set([
            'cache.default' => 'array',
            'nvl-content.media.enabled' => false,
            'nvl-translatable.locales' => ['en'],
            'nvl-content.locales.available' => ['en'],
            'nvl-content.definitions' => [
                'standalone-copy' => [
                    'name' => 'Standalone copy', 'category' => 'testing', 'version' => 1,
                    'schema' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title', 'localized' => true]]],
                ],
            ],
        ]);
    }
}
