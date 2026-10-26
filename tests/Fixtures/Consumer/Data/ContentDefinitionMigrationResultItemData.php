<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * One successfully committed block definition upgrade.
 *
 * @api
 */
final class ContentDefinitionMigrationResultItemData extends Data
{
    use DataTransform;

    public function __construct(public readonly string $blockId, public readonly string $definition, public readonly int $fromVersion, public readonly int $toVersion, public readonly int $previousRevision, public readonly int $revision) {}
}
