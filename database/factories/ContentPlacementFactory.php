<?php

declare(strict_types=1);

namespace Nvl\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentOwner;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;

/**
 * Builds ContentPlacement fixture rows and their declared package parents.
 *
 * @extends Factory<ContentPlacement>
 *
 * @api
 */
final class ContentPlacementFactory extends Factory
{
    protected $model = ContentPlacement::class;

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
        })->afterMaking(function (ContentPlacement $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            $owner = FactoryGuard::owner($model, 'owner_type', 'owner_id');
            if (! $owner instanceof ContentOwner || ! in_array($model->group, $owner->contentGroups(), true)) {
                throw new InvalidArgumentException('Content placement fixtures require a native owner composition group.');
            }
            FactoryGuard::inherit($model, $owner);
            if ($model->getAttribute('content_block_id') !== null) {
                $parent = ContentBlock::query()->findOrFail(FactoryGuard::identifier($model->getAttribute('content_block_id')));
                FactoryGuard::parent($parent, $model);
                if (config('nvl-tenancy.enabled') === true && $owner->getRawOriginal('tenant_id') !== $parent->getRawOriginal('tenant_id')) {
                    throw new InvalidArgumentException('Fixture owner and parent tenancy must agree.');
                }
                FactoryGuard::inherit($model, $parent);
            }
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<ContentPlacement>, mixed>
     */
    public function definition(): array
    {
        return [
            'content_block_id' => ContentBlock::factory(),
            'owner_type' => null,
            'owner_id' => null,
            'group' => 'default',
            'key' => $this->faker->unique()->slug(3),
            'region' => 'main',
            'revision' => 1,
        ];
    }

    /**
     * Associate an admitted persisted ContentBlock parent.
     *
     * @api
     */
    public function forBlock(ContentBlock $parent): static
    {
        FactoryGuard::parent($parent, new ContentPlacement);

        return $this->state([
            'content_block_id' => $parent->getKey(),
        ]);
    }

    /**
     * Associate a persisted host owner using its native morph identity.
     *
     * @api
     */
    public function forOwner(Model $owner): static
    {
        FactoryGuard::parent($owner, new ContentPlacement);

        return $this->state([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) FactoryGuard::identifier($owner->getKey()),
        ]);
    }
}
