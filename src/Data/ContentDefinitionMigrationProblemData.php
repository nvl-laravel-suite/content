<?php

declare(strict_types=1);

namespace Nvl\Content\Data;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Safe reason why one block cannot enter a definition migration batch.
 *
 * @api
 */
final class ContentDefinitionMigrationProblemData extends Data
{
    use DataTransform;

    public function __construct(
        public readonly string $blockId,
        public readonly string $blockKey,
        public readonly string $definition,
        public readonly int $fromVersion,
        public readonly int $toVersion,
        public readonly int $expectedRevision,
        public readonly bool $deleted,
        public readonly string $code,
        public readonly string $message,
    ) {}
}
