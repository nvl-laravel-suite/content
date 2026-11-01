<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Models\ContentBlock;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/** Explicitly authorizes only trusted background operations in the lean test host. */
final class StandaloneContentAuthorization implements ContentAuthorization
{
    /** @param array<string, mixed> $context */
    public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
    {
        if ($actor !== SystemActor::Background) {
            throw new AuthorizationException('The lean Content fixture admits only its trusted background actor.');
        }
    }
}
