<?php

declare(strict_types=1);

namespace Nvl\Content\Events;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Enums\ContentRevisionEvent;
use Nvl\Support\Contracts\DomainEvent;

/**
 * Stable after-commit mutation event.
 *
 * @api
 */
final class ContentBlockChanged implements DomainEvent
{
    public function __construct(
        public readonly string $blockId,
        public readonly ContentRevisionEvent $event,
        public readonly int $revision,
        public readonly ContentActorData $actor,
        public readonly int $schemaVersion = 1,
    ) {}

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
