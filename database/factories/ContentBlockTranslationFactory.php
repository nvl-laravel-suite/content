<?php

declare(strict_types=1);

namespace Nvl\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentBlockTranslation;

/**
 * Builds ContentBlockTranslation fixture rows and their declared package parents.
 *
 * @extends Factory<ContentBlockTranslation>
 *
 * @api
 */
final class ContentBlockTranslationFactory extends Factory
{
    protected $model = ContentBlockTranslation::class;

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
        })->afterMaking(function (ContentBlockTranslation $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            if ($model->getAttribute('content_block_id') !== null) {
                $parent = ContentBlock::query()->findOrFail(FactoryGuard::identifier($model->getAttribute('content_block_id')));
                FactoryGuard::parent($parent, $model);
                FactoryGuard::inherit($model, $parent);
            }
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<ContentBlockTranslation>, mixed>
     */
    public function definition(): array
    {
        return [
            'content_block_id' => ContentBlock::factory(),
            'locale' => 'en',
            'values' => [],
        ];
    }

    /**
     * Associate an admitted persisted ContentBlock parent.
     *
     * @api
     */
    public function forBlock(ContentBlock $parent): static
    {
        FactoryGuard::parent($parent, new ContentBlockTranslation);

        return $this->state([
            'content_block_id' => $parent->getKey(),
        ]);
    }
}
