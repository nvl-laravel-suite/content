<?php

declare(strict_types=1);

namespace Nvl\Content\Data;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden as TypeScriptHidden;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Immutable JSON-safe composition source used by versioning consumers.
 *
 * @api
 */
#[TypeScript]
final class ContentCompositionSnapshotData extends Data
{
    use DataTransform;

    /**
     * Retain immutable composition values and their declared durable ownership data.
     *
     * @param  list<ContentCompositionSnapshotBlockData>  $blocks
     * @param  array<string,mixed>|null  $ownershipContext
     */
    public function __construct(
        public readonly string $ownerType,
        public readonly string $ownerId,
        public readonly string $group,
        #[DataCollectionOf(ContentCompositionSnapshotBlockData::class)]
        public readonly array $blocks,
        public readonly string $version,
        #[TypeScriptHidden]
        public readonly ?string $tenantId = null,
        public readonly int $formatVersion = 1,
        public readonly ?array $ownershipContext = null,
    ) {}
}
