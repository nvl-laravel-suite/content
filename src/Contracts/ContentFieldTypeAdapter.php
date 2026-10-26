<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Validation\ContentValidationContext;

/**
 * Pluggable field validation, normalization, and display contract.
 *
 * @api
 */
interface ContentFieldTypeAdapter
{
    /** Native Content operation. */
    public function alias(): string;

    /** Native Content operation. */
    public function normalize(mixed $value, ContentFieldDefinition $field, ContentValidationContext $context): mixed;

    /** Native Content operation. */
    public function render(mixed $value, ContentFieldDefinition $field, ContentValidationContext $context): mixed;
}
