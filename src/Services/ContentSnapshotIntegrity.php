<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Nvl\Content\Contracts\ContentSnapshotIntegrity as ContractsContentSnapshotIntegrity;
use Nvl\Content\Support\ContentArrays;
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

    /** Reject a snapshot whose canonical checksum has changed.
     * @param  array<string,mixed>  $snapshot
     */
    public function assertValid(array $snapshot): void
    {
        foreach (['ownerType', 'ownerId', 'group', 'version'] as $key) {
            if (! isset($snapshot[$key]) || ! is_string($snapshot[$key])) {
                throw new OwnershipViolation('Content snapshot identity must contain strings.');
            }
        }
        if (! isset($snapshot['formatVersion']) || ! is_int($snapshot['formatVersion']) || ! array_key_exists('tenantId', $snapshot) || ($snapshot['tenantId'] !== null && ! is_string($snapshot['tenantId'])) || ! array_key_exists('ownershipContext', $snapshot) || ($snapshot['ownershipContext'] !== null && ! is_array($snapshot['ownershipContext'])) || ! isset($snapshot['blocks']) || ! is_array($snapshot['blocks']) || ! array_is_list($snapshot['blocks'])) {
            throw new OwnershipViolation('Content snapshot metadata is malformed.');
        }
        foreach ($snapshot['blocks'] as $block) {
            if (! is_array($block)) {
                throw new OwnershipViolation('Content snapshot blocks must be maps.');
            }
            foreach (['placementId', 'key', 'region', 'blockId', 'definitionKey', 'visibility'] as $key) {
                if (! isset($block[$key]) || ! is_string($block[$key])) {
                    throw new OwnershipViolation('Content snapshot block identity must contain strings.');
                }
            }
            foreach (['parentId', 'definitionView'] as $key) {
                if (! array_key_exists($key, $block) || ($block[$key] !== null && ! is_string($block[$key]))) {
                    throw new OwnershipViolation('Content snapshot optional text is malformed.');
                }
            }
            foreach (['sortOrder', 'blockRevision', 'placementRevision'] as $key) {
                if (! isset($block[$key]) || ! is_int($block[$key])) {
                    throw new OwnershipViolation('Content snapshot revisions must be integers.');
                }
            }
            foreach (['definitionSchema', 'values', 'translations', 'overrides'] as $key) {
                if (! isset($block[$key]) || ! is_array($block[$key])) {
                    throw new OwnershipViolation('Content snapshot values must be maps.');
                }
                ContentArrays::stringMap($block[$key], 'snapshot '.$key);
            }
            ContentArrays::translations($block['translations'], 'snapshot translations');
        }

        /** @var array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null} $snapshot */
        if (! in_array($snapshot['formatVersion'], [1, 2, 3], true)) {
            throw new OwnershipViolation('Unsupported Content snapshot format.');
        }
        if (! hash_equals($snapshot['version'], $this->digest($snapshot))) {
            throw new OwnershipViolation('Content snapshot checksum is invalid.');
        }
    }

    /** Compute the canonical content and ownership checksum.
     * @param  array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}  $snapshot
     */
    public function digest(array $snapshot): string
    {
        $payload = ['owner_type' => $snapshot['ownerType'], 'owner_id' => $snapshot['ownerId'], 'group' => $snapshot['group'], 'blocks' => array_map(static fn (array $b): array => $b, $snapshot['blocks'])];
        if ($snapshot['formatVersion'] >= 3) {
            $payload = ['format_version' => $snapshot['formatVersion'], 'ownership_context' => $snapshot['ownershipContext'], ...$payload];
        } elseif ($snapshot['formatVersion'] >= 2) {
            $payload = ['format_version' => $snapshot['formatVersion'], 'tenant_id' => $snapshot['tenantId'], ...$payload];
        }

        return $this->json->hash($payload);
    }

    /** Rebind only after the caller has separately authorized the target or reviewed its adoption mapping.
     * @param  array<string,mixed>  $context
     * @param  list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>|null  $blocks
     * @param  array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}  $source
     * @return array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}
     */
    public function rebind(array $source, string $type, string $id, array $context, ?array $blocks = null): array
    {
        $this->assertValid($source);
        $snapshot = ['ownerType' => $type, 'ownerId' => $id, 'group' => $source['group'], 'blocks' => $blocks ?? $source['blocks'], 'version' => 'pending', 'tenantId' => null, 'formatVersion' => 3, 'ownershipContext' => $context];

        return ['ownerType' => $type, 'ownerId' => $id, 'group' => $source['group'], 'blocks' => $snapshot['blocks'], 'version' => $this->digest($snapshot), 'tenantId' => null, 'formatVersion' => 3, 'ownershipContext' => $context];
    }
}
