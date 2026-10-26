<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

/**
 * Pure snapshot hashing, with authorization owned by the calling workflow.
 *
 * @api
 */
interface ContentSnapshotIntegrity
{
    /** Reject a snapshot whose canonical checksum has changed.
     * @param  array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}  $snapshot
     */
    public function assertValid(array $snapshot): void;

    /** Compute the canonical content and ownership checksum.
     * @param  array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}  $snapshot
     */
    public function digest(array $snapshot): string;

    /** @param array<string,mixed> $context
     * @param  list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>|null  $blocks
     * @param  array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}  $source
     * @return array{ownerType:string,ownerId:string,group:string,blocks:list<array{placementId:string,parentId:string|null,key:string,region:string,sortOrder:int,blockId:string,definitionKey:string,definitionSchema:array<string,mixed>,definitionView:string|null,visibility:string,values:array<string, mixed>,translations:array<string, array<string, mixed>>,overrides:array<string, mixed>,blockRevision:int,placementRevision:int}>,version:string,tenantId:string|null,formatVersion:int,ownershipContext:array<string,mixed>|null}
     */
    public function rebind(array $source, string $type, string $id, array $context, ?array $blocks = null): array;
}
