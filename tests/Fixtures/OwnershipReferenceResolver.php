<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Contracts\OwnershipSafeContentReferenceResolver;
use Nvl\Content\Validation\ContentValidationContext;

/** Simulates a resolver returning the wrong canonical record for the requested key. */
final class OwnershipReferenceResolver implements OwnershipSafeContentReferenceResolver
{
    public static ?Model $record = null;

    public function alias(): string
    {
        return 'owned-reference';
    }

    public function exists(string $identifier, ContentValidationContext $context): bool
    {
        return true;
    }

    public function display(string $identifier, ContentValidationContext $context): array
    {
        return ['label' => 'Private projection'];
    }

    public function ownershipRecord(string $identifier, ContentValidationContext $context): ?Model
    {
        return self::$record;
    }
}
