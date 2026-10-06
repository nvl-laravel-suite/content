<?php

declare(strict_types=1);

namespace Nvl\Content\Support;

use InvalidArgumentException;
use Nvl\Content\Definitions\Tables\ContentTables;
use Nvl\Support\Config\PackageStorage;

/**
 * Typed access to package configuration used by persistence and limits.
 */
final class ContentConfiguration
{
    public static function connection(): ?string
    {
        return PackageStorage::connection('content');
    }

    public static function table(string $key): string
    {
        return ContentTables::get($key);
    }

    public static function positiveInteger(string $key, int $default): int
    {
        $value = config($key, $default);

        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("{$key} must be a positive integer.");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public static function stringList(string $key): array
    {
        $value = config($key, []);

        if (! is_array($value)) {
            throw new InvalidArgumentException("{$key} must be an array.");
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("{$key} must contain only non-empty strings.");
            }
        }

        return array_values(array_unique($value));
    }
}
