<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Nvl\Support\Enums\PublicActor;

/**
 * Maps Laravel authentication to the transport-neutral actor DTO.
 */
final class ContentActorFactory
{
    /** @return ($public is true ? Authenticatable|PublicActor : Authenticatable) */
    public function fromRequest(Request $request, bool $public = false): Authenticatable|PublicActor
    {
        $user = $request->user();

        return $user ?? ($public ? PublicActor::Anonymous : new GenericUser(['id' => null]));
    }
}
