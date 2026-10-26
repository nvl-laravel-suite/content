<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

final class TestContentMember extends User
{
    protected $table = 'content_string_test_owners';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    public function getMorphClass(): string
    {
        return 'member';
    }
}
