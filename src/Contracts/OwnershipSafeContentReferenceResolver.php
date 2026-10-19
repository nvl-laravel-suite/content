<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Validation\ContentValidationContext;

/**
 * Supplies the actual canonical record whose native ownership gates dynamic reference output.
 *
 * @api
 */
interface OwnershipSafeContentReferenceResolver extends ContentReferenceResolver
{
    /** Return the actual canonical record for the exact requested reference key. */
    public function ownershipRecord(string $identifier, ContentValidationContext $context): ?Model;
}
