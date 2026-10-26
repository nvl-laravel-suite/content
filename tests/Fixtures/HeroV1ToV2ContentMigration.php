<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Nvl\Content\Contracts\ContentDefinitionMigration;

/**
 * Representative consumer migration for the package integration suite.
 */
final class HeroV1ToV2ContentMigration implements ContentDefinitionMigration
{
    public function definitionKey(): string
    {
        return 'hero';
    }

    public function fromVersion(): int
    {
        return 1;
    }

    public function toVersion(): int
    {
        return 2;
    }

    public function migrate(array $context): array
    {
        $translations = $context['translations'];
        foreach ($translations as $locale => $values) {
            if (array_key_exists('headline', $values) && ! array_key_exists('title', $values)) {
                $values['title'] = $values['headline'];
            }
            unset($values['headline']);
            $translations[$locale] = $values;
        }

        return ['values' => $context['values'], 'translations' => $translations, 'metadata' => $context['metadata']];
    }
}
