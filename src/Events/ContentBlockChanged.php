<?php

declare(strict_types=1);

namespace Nvl\Content\Events;

use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Enums\ContentRevisionEvent;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Support\Contracts\DomainEvent;
use Nvl\Support\Ownership\Contracts\OwnershipQueuedJob;
use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;

/**
 * Stable after-commit mutation event.
 *
 * @api
 */
final class ContentBlockChanged implements DomainEvent, OwnershipQueuedJob
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(
        public readonly string $blockId,
        public readonly ContentRevisionEvent $event,
        public readonly int $revision,
        public readonly ContentActorData $actor,
        public readonly int $schemaVersion = 1,
    ) {
        $this->ownershipEnvelope = ContentOwnership::envelope();
    }

    private readonly OwnershipJobEnvelope $ownershipEnvelope;

    /** Return the scalar authority captured before queue serialization. */
    public function ownershipJobEnvelope(): OwnershipJobEnvelope
    {
        return $this->ownershipEnvelope;
    }

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
