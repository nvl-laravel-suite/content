<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Queries;

final class ExpectedRevisionData
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['expected_revision' => ['required', 'integer', 'min:1']];
    }
}
