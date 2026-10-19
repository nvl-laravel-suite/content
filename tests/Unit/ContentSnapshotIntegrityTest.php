<?php

declare(strict_types=1);

use Nvl\Content\Data\ContentCompositionSnapshotData;
use Nvl\Content\Services\CanonicalJson;
use Nvl\Content\Services\ContentSnapshotIntegrity;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

it('rejects unsupported snapshot formats even when their checksum matches', function (int $format): void {
    $integrity = new ContentSnapshotIntegrity(new CanonicalJson);
    $source = new ContentCompositionSnapshotData('example', 'snapshot-id', 'main', [], 'pending', formatVersion: $format);
    $source = new ContentCompositionSnapshotData($source->ownerType, $source->ownerId, $source->group, [], $integrity->digest($source), formatVersion: $format);
    expect(fn () => $integrity->assertValid($source))->toThrow(OwnershipViolation::class);
})->with([-1, 0, 4]);
