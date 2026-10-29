<?php

declare(strict_types=1);

namespace Nvl\Content\Tests;

use Illuminate\Console\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Schema;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Content\Tests\Fixtures\Consumer\PublishContentViewsCommand;
use Nvl\Content\Tests\Fixtures\ContentMediaAuthorization;
use Nvl\Content\Tests\Fixtures\HeroV1ToV2ContentMigration;
use Nvl\Content\Tests\Fixtures\TestContentOwner;
use Nvl\Content\Tests\Fixtures\TestReferenceResolver;
use Nvl\Media\Contracts\MediaAuthorization;
use Nvl\Media\Providers\MediaServiceProvider;
use Nvl\Media\Tests\Fixtures\Http\Controllers\MediaAssetController;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

/**
 * Boots Content with only its declared NVL foundation dependencies.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** Host-owned Media endpoints used by the optional delivery adapter fixtures. */
    protected function defineRoutes($router): void
    {
        $controller = MediaAssetController::class;
        $router->get('/nvl/media/assets/{media}', [$controller, 'showPublic'])
            ->name('nvl.media.assets.show');
        $router->get('/nvl/media/private/{owner}/{media}', [$controller, 'showPrivate'])
            ->middleware(['signed', SubstituteBindings::class])
            ->name('nvl.media.private.show');
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LaravelDataServiceProvider::class, SupportServiceProvider::class, LocaleServiceProvider::class, TranslatableServiceProvider::class, MediaServiceProvider::class, ContentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->afterResolving('view', static fn ($views) => $views->addNamespace('nvl-content', __DIR__.'/Fixtures/Consumer/views'));
        Application::starting(static fn ($artisan) => $artisan->resolve(PublishContentViewsCommand::class));
        $app['config']->set('nvl-content.rendering.strict_views', true);
        $app['config']->set('nvl-content.routes', ['management' => ['enabled' => false, 'prefix' => 'nvl/api/v1/content', 'name' => 'nvl.content.management.', 'middleware' => ['api', 'auth']], 'public' => ['enabled' => false, 'prefix' => 'nvl/api/v1/content', 'name' => 'nvl.content.public.', 'middleware' => ['api', 'throttle:120,1']]]);

        $app->bind(MediaAuthorization::class, ContentMediaAuthorization::class);
        $app['config']->set(['cache.default' => 'array', 'filesystems.default' => 'public', 'nvl-media.disk' => 'public', 'nvl-media.routes.api_enabled' => false, 'nvl-media.routes.assets_enabled' => false, 'nvl-content.media.enabled' => true, 'nvl-content.authorization.callback' => static fn (): bool => true, 'nvl-content.definition_migrations' => [HeroV1ToV2ContentMigration::class], 'nvl-content.locales.available' => ['en', 'bg'], 'nvl-translatable.locales' => ['en', 'bg'], 'nvl-content.locales.required_on_publish' => ['en'], 'nvl-content.scopes' => ['site' => ['key_pattern' => '/^[a-z0-9-]{1,50}$/']], 'nvl-content.owners' => ['page' => TestContentOwner::class], 'nvl-content.references' => ['article' => TestReferenceResolver::class], 'nvl-content.definitions' => ['hero' => ['name' => 'Hero', 'category' => 'marketing', 'version' => 2, 'allowed_scopes' => ['site'], 'allowed_regions' => ['main', 'sidebar'], 'schema' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Title', 'localized' => true, 'required' => true, 'settings' => ['max_length' => 120]], ['key' => 'body', 'type' => 'rich_text', 'label' => 'Body', 'localized' => true], ['key' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled', 'default' => true], ['key' => 'image', 'type' => 'media', 'label' => 'Image', 'settings' => ['mime_types' => ['image/jpeg']]], ['key' => 'links', 'type' => 'repeater', 'label' => 'Links', 'settings' => ['max_items' => 3], 'fields' => [['key' => 'label', 'type' => 'text', 'label' => 'Label'], ['key' => 'url', 'type' => 'url', 'label' => 'URL']]], ['key' => 'layout', 'type' => 'json', 'label' => 'Layout', 'settings' => ['schema' => ['$schema' => 'https://json-schema.org/draft/2020-12/schema', 'type' => 'object', 'properties' => ['columns' => ['type' => 'integer', 'minimum' => 1]], 'required' => ['columns'], 'additionalProperties' => false]]], ['key' => 'metrics', 'type' => 'table', 'label' => 'Metrics', 'fields' => [['key' => 'label', 'type' => 'text', 'label' => 'Label'], ['key' => 'value', 'type' => 'number', 'label' => 'Value']]], ['key' => 'article', 'type' => 'reference', 'label' => 'Article', 'settings' => ['reference_type' => 'article']]]]]]]);
    }

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('content_test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('content_integer_test_owners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('content_string_test_owners', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
}
