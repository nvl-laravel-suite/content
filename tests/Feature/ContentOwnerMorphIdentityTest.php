<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Actions\CreateContentBlockAction;
use Nvl\Content\Actions\PlaceContentBlockAction;
use Nvl\Content\Actions\SyncContentDefinitionsAction;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Data\Mutations\PlaceContentBlockData;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Tests\Fixtures\TestContentOwner;

it('writes and joins native owner identity with and without a host morph map', function (bool $mapped): void {
    $original = Relation::morphMap();
    Relation::morphMap($mapped ? ['host-article' => TestContentOwner::class] : [], false);
    try {
        $map = Relation::morphMap();
        app(SyncContentDefinitionsAction::class)->execute(ContentActorData::system());
        $owner = TestContentOwner::query()->create(['name' => 'Native identity']);
        $block = app(CreateContentBlockAction::class)->execute(
            new CreateContentBlockData(
                definition: 'hero', key: 'native-owner', scope: 'site', scopeKey: 'main-site',
                translations: ['en' => ['title' => 'Native identity']],
            ),
            ContentActorData::system(),
        );
        $placement = app(PlaceContentBlockAction::class)->execute(
            $block, $owner, 'default', new PlaceContentBlockData(key: 'native-owner'), ContentActorData::system(),
        );

        expect($placement->owner_type)->toBe($owner->getMorphClass())
            ->and($placement->owner->is($owner))->toBeTrue()
            ->and($owner->contentPlacements()->whereKey($placement->getKey())->exists())->toBeTrue()
            ->and(ContentPlacement::query()->whereHasMorph('owner', [TestContentOwner::class],
                fn ($query) => $query->whereKey($owner->getKey()))->whereKey($placement->getKey())->exists())->toBeTrue()
            ->and(app(ContentOwnerRegistry::class)->resolve($owner->getMorphClass(), (string) $owner->getKey())->is($owner))->toBeTrue()
            ->and(Relation::morphMap())->toBe($map);
    } finally {
        Relation::morphMap($original, false);
    }
})->with([false, true]);
