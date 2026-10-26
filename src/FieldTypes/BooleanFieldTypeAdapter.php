<?php

declare(strict_types=1);

namespace Nvl\Content\FieldTypes;

use InvalidArgumentException;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Validation\ContentValidationContext;

/**
 * Accepts only explicit booleans and null without truthy coercion.
 */
final class BooleanFieldTypeAdapter extends AbstractFieldTypeAdapter
{
    /** Native Content operation. */
    public function alias(): string
    {
        return 'boolean';
    }

    /** Native Content operation. */
    public function normalize(mixed $value, ContentFieldDefinition $field, ContentValidationContext $context): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }
        throw new InvalidArgumentException("Content field [{$context->path}] must be boolean.");
    }
}
