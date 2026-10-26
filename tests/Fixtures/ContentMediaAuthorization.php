<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Media\Contracts\MediaAuthorization;
use Nvl\Media\Enums\MediaAbility;
use Nvl\Media\Enums\MediaVisibility;
use Nvl\Media\Models\Media;
use Nvl\Support\Enums\SystemActor;

final class ContentMediaAuthorization implements MediaAuthorization
{
    public function allows(Authenticatable|SystemActor $actor, MediaAbility $ability, ?Media $media = null, ?Model $owner = null): bool
    {
        return $actor instanceof SystemActor || $media === null || $media->visibility === MediaVisibility::Public || ($actor instanceof Model && $media->uploaded_by_type === $actor->getMorphClass() && $media->uploaded_by === $actor->getAuthIdentifier());
    }
}
