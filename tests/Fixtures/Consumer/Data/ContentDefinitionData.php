<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Source-controlled block definition.
 *
 * @api
 */
final class ContentDefinitionData extends Data
{
    use DataTransform;

    /**
     * @param  array<string, mixed>  $defaults
     * @param  list<string>  $allowedScopes
     * @param  list<string>  $allowedRegions
     * @param  array<string, mixed>  $jsonSchema
     */
    public function __construct(public readonly string $key, public readonly string $name, public readonly ?string $description, public readonly string $category, public readonly int $version, public readonly ?string $view, public readonly ContentSchemaData $schema, public readonly array $defaults = [], public readonly array $allowedScopes = ['global'], public readonly array $allowedRegions = ['main'], public readonly bool $isActive = true, public readonly int $sortOrder = 0, public readonly array $jsonSchema = []) {}
}
