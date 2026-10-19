<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Nvl\Content\Contracts\ContentSnapshotIntegrity as ContractsContentSnapshotIntegrity;
use Nvl\Content\Data\ContentCompositionSnapshotBlockData;
use Nvl\Content\Data\ContentCompositionSnapshotData;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

/**
 * Pure snapshot hashing for reviewed adoption and explicit catalog copies; it conveys no authority.
 *
 * @api
 */
final readonly class ContentSnapshotIntegrity implements ContractsContentSnapshotIntegrity
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private CanonicalJson $json) {}

    /** Reject a snapshot whose canonical checksum has changed. */
    public function assertValid(ContentCompositionSnapshotData $snapshot): void
    {
        if (! in_array($snapshot->formatVersion,[1,2,3],true)) { throw new OwnershipViolation('Unsupported Content snapshot format.'); }
        if (! hash_equals($snapshot->version, $this->digest($snapshot))) {
            throw new OwnershipViolation('Content snapshot checksum is invalid.');
        }
    }

    /** Compute the canonical content and ownership checksum. */
    public function digest(ContentCompositionSnapshotData $snapshot): string
    {
        $payload = ['owner_type' => $snapshot->ownerType, 'owner_id' => $snapshot->ownerId, 'group' => $snapshot->group, 'blocks' => array_map(static fn (ContentCompositionSnapshotBlockData $b): array => $b->toArray(), $snapshot->blocks)];
        if ($snapshot->formatVersion >= 3) {
            $payload = ['format_version' => $snapshot->formatVersion, 'ownership_context' => $snapshot->ownershipContext, ...$payload];
        } elseif ($snapshot->formatVersion >= 2) {
            $payload = ['format_version' => $snapshot->formatVersion, 'tenant_id' => $snapshot->tenantId, ...$payload];
        }

        return $this->json->hash($payload);
    }

    /** Rebind only after the caller has separately authorized the target or reviewed its adoption mapping.
     * @param  array<string,mixed>  $context
     * @param  list<ContentCompositionSnapshotBlockData>|null  $blocks
     */
    public function rebind(ContentCompositionSnapshotData $source, string $type, string $id, array $context, ?array $blocks = null): ContentCompositionSnapshotData
    {
        $this->assertValid($source);
        $snapshot = new ContentCompositionSnapshotData($type, $id, $source->group, $blocks ?? $source->blocks, 'pending', null, 3, $context);

        return new ContentCompositionSnapshotData($type, $id, $source->group, $snapshot->blocks, $this->digest($snapshot), null, 3, $context);
    }
}
