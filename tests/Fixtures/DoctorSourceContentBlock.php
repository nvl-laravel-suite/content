<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Nvl\Content\Models\ContentBlock;

/** A consumer root whose explicit source takes precedence over package configuration. */
class DoctorSourceContentBlock extends ContentBlock
{
    protected $connection = 'content_doctor_source';
}
