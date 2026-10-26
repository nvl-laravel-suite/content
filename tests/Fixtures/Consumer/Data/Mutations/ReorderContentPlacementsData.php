<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations;

use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Complete owner-group placement tree proposal.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class ReorderContentPlacementsData extends Data
{
    use DataTransform;

    /**
     * @param  list<ReorderContentPlacementData>  $placements
     */
    public function __construct(
        #[DataCollectionOf(ReorderContentPlacementData::class)]
        public readonly array $placements
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $maximum = ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000);

        return ['placements' => ['present', 'array', "max:{$maximum}"]];
    }
}
