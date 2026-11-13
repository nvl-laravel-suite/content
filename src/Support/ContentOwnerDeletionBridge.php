<?php

declare(strict_types=1);

namespace Nvl\Content\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Nvl\Content\Services\ContentOwnerDeletion;

/**
 * Connects manually instantiated Eloquent owners to the provider-owned cleanup runtime.
 */
final class ContentOwnerDeletionBridge
{
    /** @var (Closure(): ContentOwnerDeletion)|null */
    private static ?Closure $resolver = null;

    /**
     * Attach the deletion runtime supplied by the current Content provider.
     */
    public static function use(ContentOwnerDeletion $runtime): void
    {
        self::$resolver = static fn (): ContentOwnerDeletion => $runtime;
    }

    /**
     * Attach a lazy resolver so each Octane request resolves the active sandbox runtime.
     *
     * @param  Closure(): ContentOwnerDeletion  $resolver
     */
    public static function useResolver(Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Release any runtime retained from a previous application lifecycle.
     */
    public static function clear(): void
    {
        self::$resolver = null;
    }

    /**
     * Delete one owner through the runtime without retaining a stale container instance.
     *
     * @param  Closure(): (bool|null)  $delete
     */
    public static function delete(Model $owner, Closure $delete): ?bool
    {
        $resolver = self::$resolver ?? throw new LogicException('Content owner deletion requires the booted Content provider.');

        return $resolver()->execute($owner, $delete);
    }
}
