<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentPlacements;
use Nvl\Content\Tests\Fixtures\TestContentOwner;
use Nvl\Support\Enums\SystemActor;

it('writes and joins native owner identity with and without a host morph map', function (bool $mapped): void {
    $original = Relation::morphMap();
    Relation::morphMap($mapped ? ['host-article' => TestContentOwner::class] : [], false);
    try {
        $map = Relation::morphMap();
        app(ContentDefinitions::class)->sync(SystemActor::Background);
        $owner = TestContentOwner::query()->create(['name' => 'Native identity']);
        $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'native-owner', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => ['en' => ['title' => 'Native identity']], 'metadata' => []], SystemActor::Background);
        $placement = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'native-owner', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], SystemActor::Background);
        expect($placement->owner_type)->toBe($owner->getMorphClass())->and($placement->owner->is($owner))->toBeTrue()->and($owner->contentPlacements()->whereKey($placement->getKey())->exists())->toBeTrue()->and(ContentPlacement::query()->whereHasMorph('owner', [TestContentOwner::class], fn ($query) => $query->whereKey($owner->getKey()))->whereKey($placement->getKey())->exists())->toBeTrue()->and(app(ContentOwnerRegistry::class)->resolve($owner->getMorphClass(), (string) $owner->getKey())->is($owner))->toBeTrue()->and(Relation::morphMap())->toBe($map);
    } finally {
        Relation::morphMap($original, false);
    }
})->with([false, true]);
