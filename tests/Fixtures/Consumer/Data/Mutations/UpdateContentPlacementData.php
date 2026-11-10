<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Mutations;

use Nvl\Content\Validation\ContentObjectRule;

/**
 * Revision-safe contract for moving, ordering, hiding, or overriding a placement.
 *
 * @api
 */
final class UpdateContentPlacementData
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return ['expected_revision' => ['required', 'integer', 'min:1'], 'region' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'parentId' => ['nullable', 'uuid'], 'sortOrder' => ['required', 'integer', 'min:0'], 'isVisible' => ['required', 'boolean'], 'overrides' => ['array', new ContentObjectRule]];
    }
}
