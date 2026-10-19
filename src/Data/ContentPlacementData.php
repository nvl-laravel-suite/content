<?php

declare(strict_types=1);

namespace Nvl\Content\Data;

use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\Optional as TypeScriptOptional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Privileged editable placement representation for headless management.
 *
 * @api
 */
#[TypeScript]
final class ContentPlacementData extends Data
{
    use DataTransform;

    /**
     * Retain the canonical services and immutable inputs for this workflow.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function __construct(
        public readonly string $id,
        public readonly string $blockId,
        public readonly string $ownerType,
        public readonly string $ownerId,
        public readonly string $group,
        public readonly string $key,
        public readonly ?string $parentId,
        public readonly string $region,
        public readonly int $sortOrder,
        public readonly bool $isVisible,
        #[LiteralTypeScriptType('Record<string, unknown>')]
        public readonly array $overrides,
        public readonly int $revision,
        #[TypeScriptOptional]
        public readonly ?ContentBlockData $block = null,
    ) {}

    /**
     * Build the package projection from its internal storage state.
     *
     * @internal
     */
    public static function fromModel(ContentPlacement $placement): self
    {
        $relatedBlock = $placement->relationLoaded('block')
            ? $placement->getRelation('block')
            : null;

        return self::project($placement, $relatedBlock instanceof ContentBlock ? ContentBlockData::fromModel($relatedBlock) : null);
    }

    /**
     * Use only after the batch reader has scoped SQL and authorized every canonical host.
     *
     * @internal
     */
    public static function fromBatchProjection(ContentPlacement $placement): self
    {
        $block = $placement->getRelation('block');
        if (! $block instanceof ContentBlock) {
            throw new \InvalidArgumentException('Content batch placements require their scoped block.');
        }

        return self::project($placement, ContentBlockData::fromBatchProjection($block));
    }

    private static function project(ContentPlacement $placement, ?ContentBlockData $block): self
    {
        return new self(
            id: $placement->id,
            blockId: $placement->content_block_id,
            ownerType: $placement->owner_type,
            ownerId: $placement->owner_id,
            group: $placement->group,
            key: $placement->key,
            parentId: $placement->parent_id,
            region: $placement->region,
            sortOrder: $placement->sort_order,
            isVisible: $placement->is_visible,
            overrides: is_array($placement->overrides) ? $placement->overrides : [],
            revision: $placement->revision,
            block: $block,
        );
    }
}
