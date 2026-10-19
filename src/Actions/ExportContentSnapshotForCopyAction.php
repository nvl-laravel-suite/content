<?php

declare(strict_types=1);

namespace Nvl\Content\Actions;

use Nvl\Content\Contracts\ExportContentSnapshotForCopyContract;
use Nvl\Content\Contracts\OwnershipContentCatalogCopyAccess;
use Nvl\Content\Data\ContentCompositionSnapshotData;
use Nvl\Content\Data\ContentSnapshotCopyData;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Content\Services\ContentCatalogCopyRegistry;
use Nvl\Content\Services\ContentMediaReferences;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentSnapshotIntegrity;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Tenancy\Contracts\TenantContext;
use RuntimeException;

/**
 * Export an admitted catalog owner snapshot for copying into the active tenant.
 *
 * @api
 */
final readonly class ExportContentSnapshotForCopyAction implements ExportContentSnapshotForCopyContract
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(
        private ContentCatalogCopyRegistry $copies,
        private ContentOwnerRegistry $owners,
        private ContentMediaReferences $media,
        private TenantContext $context,
        private ContentSnapshotIntegrity $integrity,
    ) {}

    /** Export one exact capability-authorized immutable snapshot and its Media identities. */
    public function execute(string $ownerAlias, string $ownerId, string $group, string $grantId): ContentSnapshotCopyData
    {
        $access = $this->copies->resolve($ownerAlias);
        if (ContentOwnership::declared()) {
            if (! $access instanceof OwnershipContentCatalogCopyAccess) {
                throw new OwnershipViolation('Native Content catalog copies require a canonical source authorizer.');
            }
            $owner = $access->canonicalSource($ownerId, $group, $grantId);
            if ($owner::class !== $this->owners->model($ownerAlias) || $owner->getKey() !== $ownerId) {
                throw new OwnershipViolation('The catalog authorizer returned another source identity.');
            }
        } else {
            $destination = $this->context->requireTenant();
            $access->assertAllowed($ownerId, $group, $grantId, $destination);
            $model = $this->owners->model($ownerAlias);
            $owner = $model::query()->whereKey($ownerId)->firstOrFail();
        }
        $snapshot = $owner->getAttribute('content_snapshot');
        $revision = $owner->getAttribute('revision');
        if (! $snapshot instanceof ContentCompositionSnapshotData || ! is_int($revision)) {
            throw new RuntimeException('Catalog Content owner has no immutable snapshot.');
        }
        if (ContentOwnership::declared()) {
            $this->integrity->assertValid($snapshot);
            if ($snapshot->ownerType !== $owner->getMorphClass() || $snapshot->ownerId !== $ownerId || $snapshot->group !== $group || $snapshot->formatVersion !== 3 || $snapshot->tenantId !== null || $snapshot->ownershipContext !== ['mode' => 'central', 'owner' => null]) {
                throw new OwnershipViolation('Catalog Content must be an exact native central snapshot.');
            }
        }
        $mediaIds = [];
        foreach ($snapshot->blocks as $block) {
            $schema = $block->definitionSchema->toSchema();
            foreach ([$block->values, $block->overrides, ...array_values($block->translations)] as $values) {
                foreach ($this->media->extract($schema, $values, null) as $reference) {
                    $mediaIds[$reference['id']] = true;
                }
            }
        }

        return new ContentSnapshotCopyData($ownerAlias, $ownerId, $group, $grantId, $revision, $snapshot->version, $snapshot, array_keys($mediaIds));
    }
}
