<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nvl\Content\Facades\Content;
use Nvl\Content\Services\CanonicalJson;
use Nvl\Content\Tests\Fixtures\TenantScenario;
use Nvl\Tenancy\Exceptions\TenantBoundaryViolation;

beforeEach(function (): void {
    $this->scenario = TenantScenario::install();
    $this->actor = SystemActor::Background;
});
it('converts a hash-valid legacy snapshot only under its canonical owner tenant', function (): void {
    $owner = $this->scenario->owner(TenantScenario::A, 'Legacy owner');
    $legacy = ['ownerType' => 'tenant-owner', 'ownerId' => $owner->id, 'group' => 'default', 'blocks' => [], 'version' => app(CanonicalJson::class)->hash(['owner_type' => 'tenant-owner', 'owner_id' => $owner->id, 'group' => 'default', 'blocks' => []]), 'tenantId' => null, 'formatVersion' => 1, 'ownershipContext' => null];
    $adopted = $this->scenario->run(TenantScenario::A, static fn () => Content::adoptSnapshot($legacy));
    expect($adopted->formatVersion)->toBe(2)->and($adopted->tenantId)->toBe(TenantScenario::A)->and($adopted->version)->not->toBe($legacy['version']);
});
it('captures format two snapshots and denies foreign owners blocks and snapshots', function (): void {
    $ownerA = $this->scenario->owner(TenantScenario::A, 'A');
    $ownerB = $this->scenario->owner(TenantScenario::B, 'B');
    $blockA = $this->scenario->run(TenantScenario::A, function () {
        $block = Content::createBlock(['definition' => 'hero', 'key' => 'hero', 'scope' => 'site', 'scopeKey' => 'default', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Tenant A']], 'metadata' => []], $this->actor);

        return Content::publishBlock($block, $block->revision, $this->actor);
    });
    $snapshot = $this->scenario->run(TenantScenario::A, function () use ($blockA, $ownerA) {
        Content::place($blockA, $ownerA, 'default', ['key' => 'hero', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $this->actor);

        return Content::capture($ownerA, 'default', $this->actor, publishing: true);
    });
    expect($snapshot->formatVersion)->toBe(2)->and($snapshot->tenantId)->toBe(TenantScenario::A)->and($snapshot->version)->toHaveLength(64);
    $this->scenario->run(TenantScenario::B, function () use ($blockA, $ownerB, $snapshot): void {
        expect(fn () => Content::place($blockA, $ownerB, 'default', ['key' => 'foreign', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $this->actor))->toThrow(ModelNotFoundException::class)->and(fn () => Content::renderSnapshot($snapshot, 'en', $this->actor))->toThrow(TenantBoundaryViolation::class);
    });
});
