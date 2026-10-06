<?php

declare(strict_types=1);

namespace Nvl\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentRevision;

/**
 * Builds ContentRevision fixture rows and their declared package parents.
 *
 * @extends Factory<ContentRevision>
 *
 * @api
 */
final class ContentRevisionFactory extends Factory
{
    protected $model = ContentRevision::class;

    /**
     * Prepare native parent and owner facts after Laravel expands relationships.
     *
     * @internal
     */
    public function configure(): static
    {
        $expandRelationships = true;

        return $this->state(function () use (&$expandRelationships): array {
            $expandRelationships = $this->expandRelationships;

            return [];
        })->afterMaking(function (ContentRevision $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            if ($model->getAttribute('content_block_id') !== null) {
                $parent = ContentBlock::query()->findOrFail(FactoryGuard::identifier($model->getAttribute('content_block_id')));
                FactoryGuard::parent($parent, $model);
                FactoryGuard::inherit($model, $parent);
                $model->revision = $parent->revision;
                $translations = [];
                foreach ($parent->translations as $translation) {
                    $translations[$translation->locale] = $translation->values;
                }
                ksort($translations);
                $model->snapshot = [
                    'definition_id' => $parent->definition_id,
                    'key' => $parent->key,
                    'scope' => $parent->scope,
                    'scope_key' => $parent->scope_key,
                    'status' => $parent->status->value,
                    'visibility' => $parent->visibility->value,
                    'values' => $parent->values ?? [],
                    'translations' => $translations,
                    'metadata' => $parent->metadata ?? [],
                    'definition_version' => $parent->definition_version,
                    'definition_hash' => $parent->definition_hash,
                    'definition_schema' => $parent->definition_schema->toArray(),
                    'definition_view' => $parent->definition_view,
                    'revision' => $parent->revision,
                ];
            }
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<ContentRevision>, mixed>
     */
    public function definition(): array
    {
        return [
            'content_block_id' => ContentBlock::factory(),
            'revision' => 1,
            'event' => 'created',
            'snapshot' => [],
        ];
    }

    /**
     * Associate an admitted persisted ContentBlock parent.
     *
     * @api
     */
    public function forBlock(ContentBlock $parent): static
    {
        FactoryGuard::parent($parent, new ContentRevision);

        return $this->state([
            'content_block_id' => $parent->getKey(),
        ]);
    }
}
