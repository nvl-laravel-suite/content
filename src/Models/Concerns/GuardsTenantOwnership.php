<?php

declare(strict_types=1);

namespace Nvl\Content\Models\Concerns;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Support\Ownership\Compatibility\LegacyModelOwnership;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;

/** Preserves the package model hooks and delegates retained 6.x tenant behavior. */
trait GuardsTenantOwnership
{
    /** Register the package-owned query and mutation boundary. */
    protected static function bootGuardsTenantOwnership(): void
    {
        static::addGlobalScope('tenant-boundary', static function (Builder $query): void {
            if (! ContentOwnership::declared()) {
                Container::getInstance()->make(OwnershipBoundary::class)->query($query, self::TENANT_RESOURCE);
            }
        });

        static::creating(static function (Model $model): void {
            Container::getInstance()->make(LegacyModelOwnership::class)->creating($model, self::TENANT_RESOURCE, 'Content');
        });

        static::updating(static function (Model $model): void {
            if (ContentOwnership::declared()) {
                return;
            }
            Container::getInstance()->make(LegacyModelOwnership::class)->updating($model, self::TENANT_RESOURCE, 'Content');
        });
    }
}
