<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Nvl\Content\Validation\ContentObjectRule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

/**
 * Revision-safe contract for moving, ordering, hiding, or overriding a placement.
 *
 * @api
 */
final class UpdateContentPlacementData extends Data
{
    use DataTransform;

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function __construct(#[MapInputName('expected_revision')] #[MapOutputName('expected_revision')] public readonly int $expectedRevision, public readonly string $region, public readonly ?string $parentId, public readonly int $sortOrder, public readonly bool $isVisible, public readonly array $overrides = []) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['expected_revision' => ['required', 'integer', 'min:1'], 'region' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'parentId' => ['nullable', 'uuid'], 'sortOrder' => ['required', 'integer', 'min:0'], 'isVisible' => ['required', 'boolean'], 'overrides' => ['array', new ContentObjectRule]];
    }
}
