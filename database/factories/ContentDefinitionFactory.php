<?php

declare(strict_types=1);

namespace Nvl\Content\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Content\Models\ContentDefinition;

/**
 * Builds ContentDefinition fixture rows and their declared package parents.
 *
 * @extends Factory<ContentDefinition>
 *
 * @api
 */
final class ContentDefinitionFactory extends Factory
{
    protected $model = ContentDefinition::class;

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<ContentDefinition>, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(3),
            'name' => $this->faker->sentence(3),
            'version' => 1,
            'schema' => ['fields' => []],
            'defaults' => [],
            'source_hash' => hash('sha256', 'factory-content-definition'),
        ];
    }
}
