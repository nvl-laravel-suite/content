<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations;

use Illuminate\Validation\Rule;
use Nvl\Content\Enums\ContentMutationMode;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Validation\ContentObjectRule;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;

/**
 * Revision-safe patch or explicit replacement contract for editable content.
 *
 * @api
 */
final class UpdateContentBlockData
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['expected_revision' => ['required', 'integer', 'min:1'], 'mode' => ['sometimes', Rule::enum(ContentMutationMode::class)], 'visibility' => ['nullable', Rule::enum(ContentVisibility::class)], 'values' => ['array', new ContentObjectRule], 'translations' => ['array', new ContentObjectRule, new SupportedLocaleMapRule(self::contentLocales())], 'translations.*' => ['array', new ContentObjectRule], 'metadata' => ['array', new ContentObjectRule]];
    }

    /**
     * @return list<string>|null
     */
    private static function contentLocales(): ?array
    {
        $configured = ContentConfiguration::stringList('nvl-content.locales.available');

        return $configured === [] ? null : $configured;
    }
}
