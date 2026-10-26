<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Public-safe renderer-neutral block tree node.
 *
 * @api
 */
final class RenderedContentBlockData extends Data
{
    use DataTransform;

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $fieldTypes
     * @param  list<RenderedContentBlockData>  $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $placementId,
        public readonly string $definitionKey,
        public readonly ?string $key,
        public readonly string $region,
        public readonly int $sortOrder,
        public readonly string $view,
        public readonly string $locale,
        public readonly array $values,
        public readonly array $fieldTypes,
        #[DataCollectionOf(self::class)]
        public readonly array $children = []
    ) {}
}
