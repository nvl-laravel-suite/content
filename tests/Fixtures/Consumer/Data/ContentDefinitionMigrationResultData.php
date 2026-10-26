<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Atomic definition migration batch result.
 *
 * @api
 */
final class ContentDefinitionMigrationResultData extends Data
{
    use DataTransform;

    /**
     * @param  list<ContentDefinitionMigrationResultItemData>  $migrated
     */
    public function __construct(
        public readonly bool $applied,
        #[DataCollectionOf(ContentDefinitionMigrationResultItemData::class)]
        public readonly array $migrated
    ) {}
}
