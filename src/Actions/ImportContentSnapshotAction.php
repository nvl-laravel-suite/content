<?php

declare(strict_types=1);

namespace Nvl\Content\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Contracts\ContentOwner;
use Nvl\Content\Contracts\ImportContentSnapshotContract;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentCompositionSnapshotBlockData;
use Nvl\Content\Data\ContentCompositionSnapshotData;
use Nvl\Content\Data\ContentFieldDefinitionData;
use Nvl\Content\Data\ContentSnapshotCopyData;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Ownership\ContentOwnership;
use Nvl\Content\Services\CanonicalJson;
use Nvl\Content\Services\ContentCatalogCopyRegistry;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentSnapshotIntegrity;
use Nvl\Media\Services\MediaModelRegistry;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Tenancy\Contracts\TenantContext;
use Nvl\Support\Tenancy\Exceptions\TenantBoundaryViolation;

/**
 * Reidentifies a grant-authorized snapshot beneath its canonical owner.
 *
 * This deliberate Action composition revalidates a fresh public export before
 * importing its immutable snapshot.
 *
 * @api
 */
final readonly class ImportContentSnapshotAction implements ImportContentSnapshotContract
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(
        private ContentCatalogCopyRegistry $copies,
        private ContentOwnerRegistry $owners,
        private CanonicalJson $json,
        private TenantContext $context,
        private OwnershipBoundary $boundary,
        private ExportContentSnapshotForCopyAction $export,
        private ContentSnapshotIntegrity $integrity,
        private ContentAuthorization $authorization,
    ) {}

    /** @param array<string,string> $mediaMap */
    public function execute(
        Model&ContentOwner $target,
        ContentSnapshotCopyData $source,
        array $mediaMap,
        ContentActorData $actor,
    ): ContentCompositionSnapshotData {
        $native = ContentOwnership::snapshotContext();
        if ($native !== null) {
            $target = $this->owners->canonical($target);
            if ($this->export->execute($source->ownerAlias, $source->ownerId, $source->group, $source->grantId) != $source) {
                throw new OwnershipViolation('Content catalog snapshot is stale or forged.');
            }
            $this->authorization->authorize(ContentAbility::Render, $actor, owner: $target, context: ['snapshot' => 'import']);
            foreach ($source->snapshot->blocks as $block) {
                $this->assertPortable($block->definitionSchema->fields);
            }
            $tenant = null;
        } else {
            $tenant = $this->context->requireTenant();
            $this->copies->resolve($source->ownerAlias)->assertAllowed($source->ownerId, $source->group, $source->grantId, $tenant);
        }
        $targetType = $this->owners->type($target);
        $targetId = $this->owners->id($target);
        $this->owners->assertGroup($target, $source->group);
        foreach ($source->mediaIds as $sourceId) {
            $destinationId = $mediaMap[$sourceId]
                ?? throw new TenantBoundaryViolation("Missing copied Media mapping for [{$sourceId}].");
            $this->boundary->query(MediaModelRegistry::query(), 'media.assets')->whereKey($destinationId)->firstOrFail();
        }
        $placementIds = [];
        $blockIds = [];
        foreach ($source->snapshot->blocks as $block) {
            $placementIds[$block->placementId] = (string) Str::uuid();
            $blockIds[$block->blockId] = (string) Str::uuid();
        }
        $blocks = array_map(function (ContentCompositionSnapshotBlockData $block) use ($placementIds, $blockIds, $mediaMap): ContentCompositionSnapshotBlockData {
            $data = $this->replaceMedia($block->toArray(), $mediaMap);
            $data['placement_id'] = $placementIds[$block->placementId];
            $data['parent_id'] = $block->parentId === null
                ? null
                : ($placementIds[$block->parentId] ?? throw new TenantBoundaryViolation('Content snapshot parent is missing.'));
            $data['block_id'] = $blockIds[$block->blockId];

            return ContentCompositionSnapshotBlockData::from($data);
        }, $source->snapshot->blocks);
        if ($native !== null) {
            return $this->integrity->rebind($source->snapshot, $targetType, $targetId, $native, $blocks);
        }
        $payload = [
            'format_version' => 2,
            'tenant_id' => $tenant->value,
            'owner_type' => $targetType,
            'owner_id' => $targetId,
            'group' => $source->group,
            'blocks' => array_map(static fn (ContentCompositionSnapshotBlockData $block): array => $block->toArray(), $blocks),
        ];

        return new ContentCompositionSnapshotData(
            $targetType,
            $targetId,
            $source->group,
            $blocks,
            $this->json->hash($payload),
            $tenant->value,
            2,
        );
    }

    /** @param list<ContentFieldDefinitionData> $fields */
    private function assertPortable(array $fields): void
    {
        foreach ($fields as $field) {
            if (in_array($field->type, ['reference', 'reference_list'], true)) {
                throw new OwnershipViolation('Content catalog references require explicit target mappings; this copy has none.');
            }
            $this->assertPortable($field->fields);
            if ($field->item !== null) {
                $this->assertPortable([$field->item]);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<string, string>  $mediaMap
     * @return array<array-key, mixed>
     */
    private function replaceMedia(array $value, array $mediaMap): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->replaceMedia($item, $mediaMap);
            } elseif (is_string($item)) {
                $value[$key] = $mediaMap[$item] ?? $item;
            }
        }

        return $value;
    }
}
