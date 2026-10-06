<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Support\Config\PackageOptions;
use Nvl\Support\Tenancy\Contracts\TenantBoundary;

/**
 * Serializes placement tree mutations on a stable owner-group lock key.
 */
final readonly class ContentPlacementOwnerLock
{
    public function __construct(private Repository $cache, private TenantBoundary $tenancy) {}

    /**
     * Run one placement tree mutation under its owner-group atomic lock.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(
        string $ownerType,
        string $ownerId,
        string $group,
        Closure $callback,
    ): mixed {
        $name = PackageOptions::lockStore('content', 'placements');
        $repository = $name === config('cache.default') ? $this->cache : Cache::store($name);
        $store = $repository->getStore();

        if (! $store instanceof LockProvider) {
            throw new InvalidArgumentException(
                'The configured cache store must support atomic locks for Content placements.',
            );
        }

        $seconds = ContentConfiguration::positiveInteger(
            'content.placements.lock_seconds',
            30,
        );
        $wait = ContentConfiguration::positiveInteger(
            'content.placements.lock_wait_seconds',
            10,
        );
        $key = $this->tenancy->key('content.placements', 'nvl:content:placement-owner:'.hash(
            'sha256',
            "{$ownerType}\0{$ownerId}\0{$group}",
        ));

        return $store->lock($key, $seconds)->block($wait, $callback);
    }
}
