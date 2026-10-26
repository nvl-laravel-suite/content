<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class PlainContentOwner extends Model
{
    protected $table = 'content_string_test_owners';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;
}
