<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data\Queries;

final class ContentLocaleQueryData
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['locale' => ['nullable', 'string', 'max:35']];
    }
}
