<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Nvl\Content\Services\ContentDefinitionSyncLock;
use Nvl\Content\Services\ContentPlacementOwnerLock;
use Nvl\Content\Support\ContentRouteConfiguration;

it('inherits shared content middleware without broadening an explicit empty list', function (): void {
    config([
        'content.routes.management.middleware' => null,
        'content.routes.middleware' => null,
        'nvl-core.routes.middleware' => ['api', 'auth'],
        'nvl-core.authorization.guard' => 'admin',
    ]);
    expect(ContentRouteConfiguration::middleware('management'))->toBe(['api', 'auth:admin']);
    config(['content.routes.management.middleware' => []]);
    expect(ContentRouteConfiguration::middleware('management'))->toBe([]);
});

it('uses the shared Content lock store for definition sync and placement mutations', function (): void {
    config([
        'content.locks.store' => null,
        'nvl-core.locks.store' => 'shared-content-locks',
        'cache.stores.shared-content-locks' => ['driver' => 'array'],
    ]);
    $repository = Cache::store('shared-content-locks');
    Cache::shouldReceive('store')->with('shared-content-locks')->twice()->andReturn($repository);

    expect(app(ContentDefinitionSyncLock::class)->run(fn (): string => 'definitions'))->toBe('definitions')
        ->and(app(ContentPlacementOwnerLock::class)->run('article', 'owner-id', 'main', fn (): string => 'placements'))
        ->toBe('placements');
});
