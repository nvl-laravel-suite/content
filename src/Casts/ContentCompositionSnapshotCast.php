<?php

declare(strict_types=1);

namespace Nvl\Content\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Nvl\Content\Support\ContentArrays;

/**
 * Persists a typed immutable Content composition snapshot as deterministic JSON.
 *
 * @implements CastsAttributes<array<string,mixed>|null, mixed>
 *
 * @api
 */
final class ContentCompositionSnapshotCast implements CastsAttributes
{
    /** Native Content operation.
     * @return array<string,mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }
        $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The [{$key}] attribute must contain a Content composition snapshot.");
        }

        return ContentArrays::stringMap($decoded, 'Content snapshot');
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a Content composition snapshot.");
        }
        $snapshot = $value;

        return [$key => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
    }
}
