<?php

declare(strict_types=1);

namespace Nvl\Content\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Enums\ContentRevisionEvent;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Support\Contracts\DomainEvent;
use Nvl\Support\Enums\SystemActor;
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
    public function __construct(public readonly string $blockId, public readonly ContentRevisionEvent $event, public readonly int $revision, Authenticatable|SystemActor $actor, public readonly int $schemaVersion = 1)
    {
        $this->actor = ['type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier(), 'system' => $actor instanceof SystemActor];
        $this->ownershipEnvelope = ContentOwnership::envelope();
    }

    /** @var array{type:string,id:mixed,system:bool} */
    public readonly array $actor;

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
