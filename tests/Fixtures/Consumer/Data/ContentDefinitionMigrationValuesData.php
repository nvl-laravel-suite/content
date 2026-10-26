<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Data;

/**
 * Values returned by one deterministic definition migration step.
 *
 * @api
 */
final class ContentDefinitionMigrationValuesData extends Data
{
    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, array<string, mixed>>  $translations
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public readonly array $values, public readonly array $translations, public readonly array $metadata) {}
}
