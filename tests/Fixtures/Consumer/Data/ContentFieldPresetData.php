<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Editor-facing contract for one reusable semantic field preset.
 *
 * @api
 */
final class ContentFieldPresetData extends Data
{
    use DataTransform;

    /**
     * @param  array<string, mixed>  $jsonSchema
     */
    public function __construct(public readonly string $alias, public readonly string $name, public readonly ?string $description, public readonly ContentFieldDefinitionData $field, public readonly array $jsonSchema) {}
}
