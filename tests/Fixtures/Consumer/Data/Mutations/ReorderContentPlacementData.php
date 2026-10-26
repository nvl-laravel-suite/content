<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Revision-safe proposed tree coordinates for one Content placement.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class ReorderContentPlacementData extends Data
{
    use DataTransform;

    public function __construct(public readonly string $id, #[MapInputName('expected_revision')] #[MapOutputName('expected_revision')] public readonly int $expectedRevision, public readonly string $region, public readonly ?string $parentId, public readonly int $sortOrder) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['id' => ['required', 'uuid'], 'expected_revision' => ['required', 'integer', 'min:1'], 'region' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'parentId' => ['nullable', 'uuid'], 'sortOrder' => ['required', 'integer', 'min:0']];
    }
}
