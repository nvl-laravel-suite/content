<?php

declare(strict_types=1);
use Nvl\Content\Services\CanonicalJson;
use Nvl\Content\Services\ContentSnapshotIntegrity;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

it('rejects unsupported snapshot formats even when their checksum matches', function (int $format): void {
    $integrity = new ContentSnapshotIntegrity(new CanonicalJson);
    $source = ['ownerType' => 'example', 'ownerId' => 'snapshot-id', 'group' => 'main', 'blocks' => [], 'version' => 'pending', 'tenantId' => null, 'formatVersion' => $format, 'ownershipContext' => null];
    $source = ['ownerType' => $source['ownerType'], 'ownerId' => $source['ownerId'], 'group' => $source['group'], 'blocks' => [], 'version' => $integrity->digest($source), 'tenantId' => null, 'formatVersion' => $format, 'ownershipContext' => null];
    expect(fn () => $integrity->assertValid($source))->toThrow(OwnershipViolation::class);
})->with([-1, 0, 4]);
