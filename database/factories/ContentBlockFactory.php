<?php

declare(strict_types=1);

namespace Nvl\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentDefinition;

/**
 * Builds ContentBlock fixture rows and their declared package parents.
 *
 * @extends Factory<ContentBlock>
 *
 * @api
 */
final class ContentBlockFactory extends Factory
{
    protected $model = ContentBlock::class;

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
        })->afterMaking(function (ContentBlock $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            FactoryGuard::root($model, 'content.blocks');
            if ($model->getAttribute('definition_id') !== null) {
                $parent = ContentDefinition::query()->findOrFail(FactoryGuard::identifier($model->getAttribute('definition_id')));
                FactoryGuard::parent($parent, $model);
                if ($model->definition_version !== $parent->version || $model->definition_hash !== $parent->source_hash
                    || $model->definition_schema->toArray() !== $parent->schema->toArray()
                    || $model->definition_view !== $parent->view) {
                    throw new InvalidArgumentException('Block fixtures require the exact definition snapshot.');
                }
            }
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<ContentBlock>, mixed>
     */
    public function definition(): array
    {
        return [
            'definition_id' => ContentDefinition::factory(),
            'key' => $this->faker->unique()->slug(3),
            'scope' => 'global',
            'scope_key' => '*',
            'values' => [],
            'definition_version' => fn (array $attributes): int => $attributes['definition_id'] === null ? 1 : ContentDefinition::query()->findOrFail(FactoryGuard::identifier($attributes['definition_id']))->version,
            'definition_hash' => fn (array $attributes): string => $attributes['definition_id'] === null ? hash('sha256', 'factory-content-definition') : ContentDefinition::query()->findOrFail(FactoryGuard::identifier($attributes['definition_id']))->source_hash,
            'definition_schema' => fn (array $attributes): mixed => $attributes['definition_id'] === null ? ['fields' => []] : ContentDefinition::query()->findOrFail(FactoryGuard::identifier($attributes['definition_id']))->schema,
            'definition_view' => fn (array $attributes): ?string => $attributes['definition_id'] === null ? null : ContentDefinition::query()->findOrFail(FactoryGuard::identifier($attributes['definition_id']))->view,
            'revision' => 1,
        ];
    }

    /**
     * Associate an admitted persisted ContentDefinition parent.
     *
     * @api
     */
    public function forDefinition(ContentDefinition $parent): static
    {
        FactoryGuard::parent($parent, new ContentBlock);

        return $this->state([
            'definition_id' => $parent->getKey(),
            'definition_version' => $parent->version,
            'definition_hash' => $parent->source_hash,
            'definition_schema' => $parent->schema,
            'definition_view' => $parent->view,
        ]);
    }
}
