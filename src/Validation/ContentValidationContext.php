<?php

declare(strict_types=1);

namespace Nvl\Content\Validation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Services\ContentRenderResources;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/**
 * Immutable context shared by nested field adapters.
 *
 * @api
 */
final readonly class ContentValidationContext
{
    /** Native Content operation. */
    public function __construct(public Authenticatable|SystemActor|PublicActor $actor, public string $locale, public string $path, public ContentVisibility $visibility, public bool $publishing = false, public ?ContentRenderResources $resources = null, public ?Model $owner = null, public bool $publicOnly = false, public ?string $group = null, public bool $localized = false, public bool $resolveExternal = true) {}

    /** Native Content operation. */
    public function nested(string $segment): self
    {
        return new self(actor: $this->actor, locale: $this->locale, path: $this->path === '' ? $segment : "{$this->path}.{$segment}", visibility: $this->visibility, publishing: $this->publishing, resources: $this->resources, owner: $this->owner, publicOnly: $this->publicOnly, group: $this->group, localized: $this->localized, resolveExternal: $this->resolveExternal);
    }
}
