<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Models\ContentBlock;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/**
 * Secure default authorization adapter backed by an optional application callback.
 *
 * @internal
 */
final class ConfiguredContentAuthorization implements ContentAuthorization
{
    /** Native Content operation. */
    public function authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor, ?ContentBlock $block = null, ?Model $owner = null, array $context = []): void
    {
        $callback = config('nvl-content.authorization.callback');
        $allowed = is_callable($callback) && $callback($ability, $actor, $block, $owner, $context) === true;
        if (! $allowed) {
            throw new AuthorizationException("The actor is not authorized to [{$ability->value}] content.");
        }
    }
}
