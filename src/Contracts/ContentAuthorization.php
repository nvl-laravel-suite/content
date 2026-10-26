<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Models\ContentBlock;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/**
 * Consumer-owned policy boundary for every content capability.
 *
 * @api
 */
interface ContentAuthorization
{
    /**
     * Throw when the actor cannot perform the requested ability.
     *
     * @param  array<string, mixed>  $context
     */
    public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void;
}
