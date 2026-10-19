<?php

declare(strict_types=1);

namespace Nvl\Content\Data;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Nvl\Content\Enums\ContentStatus;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentBlockTranslation;
use Nvl\Content\Support\ContentArrays;
use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Privileged editable block representation.
 *
 * @api
 */
#[TypeScript]
final class ContentBlockData extends Data
{
    use DataTransform;

    /**
     * Retain the canonical services and immutable inputs for this workflow.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, array<string, mixed>>  $translations
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $definition,
        public readonly string $key,
        public readonly string $scope,
        public readonly string $scopeKey,
        public readonly ContentStatus $status,
        public readonly ContentVisibility $visibility,
        #[LiteralTypeScriptType('Record<string, unknown>')]
        public readonly array $values,
        #[LiteralTypeScriptType('Record<string, Record<string, unknown>>')]
        public readonly array $translations,
        #[LiteralTypeScriptType('Record<string, unknown>')]
        public readonly array $metadata,
        public readonly int $definitionVersion,
        public readonly int $revision,
        public readonly ?string $publishedAt,
    ) {}

    /**
     * Build the package projection from its internal storage state.
     *
     * @internal
     */
    public static function fromModel(ContentBlock $block): self
    {
        $block->loadMissing(['definition', 'translations']);

        return self::project($block, self::translationValues($block->translations));
    }

    /**
     * Project only freshly scoped, fully preloaded batch rows without per-record admission SQL.
     *
     * @internal
     */
    public static function fromBatchProjection(ContentBlock $block): self
    {
        if (! $block->relationLoaded('translations') || ! $block->relationLoaded('definition')) {
            throw new InvalidArgumentException('Content batch projections require preloaded structural and locale facts.');
        }
        $translations = $block->getRelation('translations');
        if (! $translations instanceof Collection) {
            throw new InvalidArgumentException('Content batch locales must be preloaded collections.');
        }

        return self::project($block, self::translationValues($translations));
    }

    /** @param Collection<covariant array-key, covariant mixed> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function translationValues(Collection $rows): array
    {
        $translations = [];
        foreach ($rows as $translation) {
            if (! $translation instanceof ContentBlockTranslation) {
                throw new InvalidArgumentException('Content locale projections require canonical locale rows.');
            }
            $locale = $translation->getAttribute('locale');
            $values = $translation->getAttribute('values');
            if (is_string($locale) && is_array($values)) {
                $translations[$locale] = ContentArrays::stringMap($values, "content translation {$locale}");
            }
        }
        ksort($translations);

        return $translations;
    }

    /** @param array<string,array<string,mixed>> $translations */
    private static function project(ContentBlock $block, array $translations): self
    {
        return new self(
            id: $block->id,
            definition: $block->definition->key,
            key: $block->key,
            scope: $block->scope,
            scopeKey: $block->scope_key,
            status: $block->status,
            visibility: $block->visibility,
            values: is_array($block->values) ? $block->values : [],
            translations: $translations,
            metadata: is_array($block->metadata) ? $block->metadata : [],
            definitionVersion: $block->definition_version,
            revision: $block->revision,
            publishedAt: $block->published_at?->toAtomString(),
        );
    }
}
