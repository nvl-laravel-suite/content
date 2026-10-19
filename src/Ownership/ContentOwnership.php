<?php

declare(strict_types=1);

namespace Nvl\Content\Ownership;

use Illuminate\Database\Eloquent\Model;
use Nvl\Support\Exceptions\BusinessException;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Contracts\OwnershipContext;
use Nvl\Support\Ownership\Enums\OwnershipContextMode;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;
use Nvl\Support\Ownership\ValueObjects\OwnershipSnapshot;
use Nvl\Support\Tenancy\Exceptions\TenantBoundaryViolation;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;

/**
 * Resolves canonical metadata without retaining request admission.
 *
 * @internal
 */
final class ContentOwnership
{
    /** Determine whether this package graph declares native primary ownership. */
    public static function declared(): bool
    {
        return (app(OwnershipResourceRegistry::class)->all()['content.blocks'] ?? null)?->ownership->strategy !== null && app(OwnershipResourceRegistry::class)->get('content.blocks')->ownership->strategy !== 'unscoped';
    }

    /** Construct the failure contract of the selected ownership authority. */
    public static function violation(string $message): BusinessException
    {
        return self::declared() ? new OwnershipViolation($message) : new TenantBoundaryViolation($message);
    }

    /** Capture identity for durable content; this is data, never an admission capability.
     * @return array<string,mixed>|null
     */
    public static function snapshotContext(): ?array
    {
        if (! self::declared()) {
            return null;
        }
        app(OwnershipBoundary::class)->key('content.blocks', 'snapshot');
        $snapshot = app(OwnershipContext::class)->snapshot();

        return ['mode' => $snapshot->mode->value, 'owner' => $snapshot->owner?->toArray()];
    }

    /** Determine whether native or legacy ownership enforcement applies. */
    public static function active(): bool
    {
        return self::declared() || config('nvl-tenancy.enabled') === true;
    }

    /** Resolve the exact canonical model to its declared ownership resource. */
    public static function resource(Model $model): string
    {
        return self::declared() ? app(OwnershipResourceRegistry::class)->forModel($model)->key : app(TenantResourceRegistry::class)->forModel($model)->key;
    }

    /** Capture scalar admission; untouched graphs preserve disabled installation behavior. */
    public static function envelope(): OwnershipJobEnvelope
    {
        $context = app(OwnershipContext::class);
        if ($context->snapshot()->mode === OwnershipContextMode::Unresolved) {
            foreach (app(OwnershipResourceRegistry::class)->all() as $resource) {
                if ($resource->ownership->strategy !== 'unscoped') {
                    return OwnershipJobEnvelope::capture($context);
                }
            }

            return OwnershipJobEnvelope::fromSnapshot(new OwnershipSnapshot(OwnershipContextMode::Disabled));
        }

        return OwnershipJobEnvelope::capture($context);
    }

    private function __construct() {}
}
