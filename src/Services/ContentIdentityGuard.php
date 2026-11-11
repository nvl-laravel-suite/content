<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Enforces the portable identifiers persisted by blocks and placements.
 *
 * @internal
 */
final class ContentIdentityGuard
{
    /**
     * Validate a stable reusable block key.
     */
    public function blockKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,190}$/D', $key) !== 1) {
            throw new InvalidArgumentException("Content block key [{$key}] is invalid.");
        }
    }

    /**
     * Treat a malformed identifier as absent; PostgreSQL rejects a non-UUID value compared with a UUID key.
     *
     * @param  class-string<Model>  $model
     */
    public function uuid(string $id, string $model): string
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel($model, [$id]);
        }

        return $id;
    }

    /**
     * Validate a stable owner alias and portable identifier.
     */
    public function owner(string $type, string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\.:-]{0,254}$/D', $type) !== 1) {
            throw new InvalidArgumentException("Content owner type [{$type}] is invalid.");
        }
        if (preg_match('/^[A-Za-z0-9_.:-]{1,191}$/D', $identifier) !== 1) {
            throw new InvalidArgumentException('Content owner identifier is invalid.');
        }
    }

    /**
     * Validate a stable placement key.
     */
    public function placementKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,190}$/D', $key) !== 1) {
            throw new InvalidArgumentException("Content placement key [{$key}] is invalid.");
        }
    }

    /**
     * Validate a stable region key.
     */
    public function region(string $region): void
    {
        $this->alias($region, 'region');
    }

    /**
     * Validate a stable composition group key.
     */
    public function group(string $group): void
    {
        $this->alias($group, 'group');
    }

    /**
     * Validate a non-negative placement order.
     */
    public function sortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new InvalidArgumentException('Content placement sort order cannot be negative.');
        }
    }

    /** Native Content operation. */
    private function alias(string $alias, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $alias) !== 1) {
            throw new InvalidArgumentException("Content {$label} [{$alias}] is invalid.");
        }
    }
}
