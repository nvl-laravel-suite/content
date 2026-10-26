<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Complete typed bootstrap contract for a consumer-owned content editor.
 *
 * @api
 */
final class ContentEditorData extends Data
{
    use DataTransform;

    /**
     * @param  list<ContentDefinitionData>  $definitions
     * @param  list<ContentFieldPresetData>  $presets
     * @param  list<string>  $groups
     * @param  list<ContentPlacementData>  $placements
     */
    public function __construct(
        public readonly string $ownerType,
        public readonly string $ownerId,
        public readonly string $group,
        #[DataCollectionOf(ContentDefinitionData::class)]
        public readonly array $definitions,
        #[DataCollectionOf(ContentFieldPresetData::class)]
        public readonly array $presets,
        public readonly array $groups,
        #[DataCollectionOf(ContentPlacementData::class)]
        public readonly array $placements,
        public readonly int $placementLimit = 1000
    ) {}
}
