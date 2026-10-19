<?php

declare(strict_types=1);

namespace Nvl\Content\Exceptions;

use Nvl\Support\Contracts\PackageException;
use RuntimeException;

/**
 * Rolls native owner cancellation through the complete source transaction before returning its result.
 *
 * @internal
 */
final class ContentOwnerDeletionCancelled extends RuntimeException implements PackageException
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(public readonly ?bool $result)
    {
        parent::__construct('Content owner deletion was canceled.');
    }
}
