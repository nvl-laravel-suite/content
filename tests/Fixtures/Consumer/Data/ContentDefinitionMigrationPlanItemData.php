<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

/**
 * Revision-safe block target in a definition migration plan.
 *
 * @api
 */
final class ContentDefinitionMigrationPlanItemData extends Data
{
    use DataTransform;

    /**
     * @param  list<int>  $versions
     */
    public function __construct(public readonly string $blockId, public readonly string $blockKey, public readonly string $definition, public readonly int $fromVersion, public readonly int $toVersion, #[MapInputName('expected_revision')] #[MapOutputName('expected_revision')] public readonly int $expectedRevision, public readonly bool $deleted, public readonly array $versions) {}
}
