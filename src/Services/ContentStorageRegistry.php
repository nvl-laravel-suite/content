<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Nvl\Content\Contracts\ContentStorage;
use Nvl\Content\Models\ContentBlock;

/**
 * Delegates integrations to the frozen configured Content root.
 *
 * @internal
 */
final readonly class ContentStorageRegistry implements ContentStorage
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private ContentModelRegistry $models) {}

    /** Return the actual source of the configured Content root. */
    public function connection(): Connection
    {
        $class = $this->models->blockClass();

        return (new $class)->getConnection();
    }

    /** Determine whether the configured Content root declares primary ownership. */
    public function owned(): bool
    {
        $class = $this->models->blockClass();

        return $class::ownershipDefinition()->strategy !== 'unscoped';
    }

    /** @return Builder<ContentBlock> */
    public function blocks(): Builder
    {
        $class = $this->models->blockClass();

        return (new $class)->newQuery();
    }
}
