<?php

declare(strict_types=1);

namespace Nvl\Content\FieldTypes;

use InvalidArgumentException;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Support\ContentUriSchemePolicy;
use Nvl\Content\Validation\ContentValidationContext;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes rich text at both write and render boundaries.
 */
final class RichTextFieldTypeAdapter extends AbstractFieldTypeAdapter
{
    private HtmlSanitizer $sanitizer;

    /** Native Content operation. */
    public function __construct()
    {
        $maximum = ContentConfiguration::positiveInteger('nvl-content.rich_text.maximum_input_length', 250000);
        $schemes = ContentUriSchemePolicy::runtimeAllowedSchemes(ContentConfiguration::stringList('nvl-content.rich_text.allowed_link_schemes'));
        $configuration = (new HtmlSanitizerConfig)->allowSafeElements()->allowLinkSchemes($schemes)->allowRelativeLinks((bool) config('nvl-content.rich_text.allow_relative_links', true))->allowMediaSchemes([])->allowRelativeMedias(false)->withMaxInputLength($maximum);
        $this->sanitizer = new HtmlSanitizer($configuration);
    }

    /** Native Content operation. */
    public function alias(): string
    {
        return 'rich_text';
    }

    /** Native Content operation. */
    public function normalize(mixed $value, ContentFieldDefinition $field, ContentValidationContext $context): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException("Content field [{$context->path}] must be HTML.");
        }
        $maximum = ContentConfiguration::positiveInteger('nvl-content.rich_text.maximum_input_length', 250000);
        if (strlen($value) > $maximum) {
            throw new InvalidArgumentException("Content field [{$context->path}] exceeds the rich-text limit.");
        }

        return $this->sanitizer->sanitize($value);
    }

    /** Native Content operation. */
    public function render(mixed $value, ContentFieldDefinition $field, ContentValidationContext $context): mixed
    {
        $normalized = $this->normalize($value, $field, $context);

        return is_string($normalized) ? ['html' => $normalized] : $normalized;
    }
}
