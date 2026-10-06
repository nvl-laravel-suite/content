<?php

declare(strict_types=1);

namespace Nvl\Content\Exceptions;

use Nvl\Support\Exceptions\BusinessException;

/**
 * @api
 * Transport-neutral package failure.
 */
class ContentException extends BusinessException
{
    /** Return the owning package namespace. */
    public function package(): string
    {
        return 'content';
    }
}
