<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Nvl\Content\Contracts\ContentStorage;
use Nvl\Support\Ownership\Contracts\OwnershipReadiness;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

/**
 * Projects only global definition-version usage for an admitted deployment-catalog update.
 *
 * @internal
 */
final readonly class ContentCodeCatalogUsage
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(
        private ContentStorage $storage,
        private ContentPlatformCatalogGuard $guard,
        private OwnershipReadiness $readiness,
    ) {}

    /** Return stored version numbers without exposing mutable owner records.
     * @return list<int>
     */
    public function versions(string $definitionId): array
    {
        $this->guard->authorize();
        $this->readiness->assertActive('content.blocks');
        $model = $this->storage->blocks()->getModel();
        $values = $this->storage->connection()->table($model->getTable())->where('definition_id', $definitionId)->distinct()->pluck('definition_version');
        $versions = [];
        foreach ($values as $value) {
            if (! is_int($value) && (! is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1)) {
                throw new OwnershipViolation('Stored Content versions must be native integers.');
            }
            $version = (int) $value;
            if ($version < 1 || (string) $version !== (string) $value) {
                throw new OwnershipViolation('Stored Content version is not canonical.');
            }
            $versions[] = $version;
        }

        return $versions;
    }
}
