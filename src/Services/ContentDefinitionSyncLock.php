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

/**
 * Serializes definition mirror synchronization across deployment processes.
 */
final readonly class ContentDefinitionSyncLock
{
    public function __construct(private Repository $cache) {}

    /**
     * Execute synchronization while holding the package-wide atomic lock.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Closure $callback): mixed
    {
        $name = PackageOptions::lockStore('content', 'definitions');
        $repository = $name === config('cache.default') ? $this->cache : Cache::store($name);
        $store = $repository->getStore();

        if (! $store instanceof LockProvider) {
            throw new InvalidArgumentException(
                'The configured cache store must support atomic locks for Content definition synchronization.',
            );
        }

        $seconds = ContentConfiguration::positiveInteger(
            'nvl-content.definition_sync.lock_seconds',
            60,
        );
        $wait = ContentConfiguration::positiveInteger(
            'nvl-content.definition_sync.lock_wait_seconds',
            10,
        );

        return $store->lock('nvl:content:definitions:sync', $seconds)
            ->block($wait, $callback);
    }
}
