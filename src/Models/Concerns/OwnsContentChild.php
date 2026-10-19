<?php

declare(strict_types=1);

namespace Nvl\Content\Models\Concerns;

use Illuminate\Container\Container;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Content\Services\ContentChildWriter;
use Nvl\Support\Ownership\Traits\HasOwnership;

/**
 * Supplies native inherited ownership to fixed internal children without bypassing creation permits.
 *
 * @internal
 */
trait OwnsContentChild
{
    use HasOwnership { save as private saveOwned; }

    /** @param array<string,mixed> $options */
    public function save(array $options = []): bool
    {
        if (! $this->exists && ContentOwnership::declared() && ! array_key_exists('owner_scope_key', $this->getAttributes())) {
            return Container::getInstance()->make(ContentChildWriter::class)->save($this, fn (): bool => $this->saveOwned($options));
        }

        return $this->saveOwned($options);
    }
}
