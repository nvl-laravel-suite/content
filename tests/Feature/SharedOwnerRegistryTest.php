<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Tests\Fixtures\TestContentOwner;
use Nvl\Content\Tests\Fixtures\TestIntegerContentOwner;
use Nvl\Support\Globals\GlobalNames;

beforeEach(function (): void {
    $this->originalOwnerMorphMap = Relation::morphMap();
});

afterEach(function (): void {
    Relation::morphMap($this->originalOwnerMorphMap, false);
});

it('resolves shared references while retaining the content contract and allowlist', function (): void {
    config()->set('nvl-core.owners', ['page' => TestContentOwner::class, 'private-page' => TestIntegerContentOwner::class]);
    $registry = app()->build(ContentOwnerRegistry::class);
    $registry->register('page', 'page');

    expect($registry->model('page'))->toBe(TestContentOwner::class)
        ->and($registry->groups(new TestContentOwner))->not->toBeEmpty()
        ->and(fn () => $registry->model('private-page'))->toThrow(InvalidArgumentException::class);
});

it('rejects a content capability key that differs from its canonical stored owner identity', function (): void {
    config()->set('nvl-core.owners', ['page' => TestContentOwner::class]);

    expect(fn () => app()->build(ContentOwnerRegistry::class)->register('page.detail', 'page'))
        ->toThrow(InvalidArgumentException::class);
});

it('preserves a host placement alias clash and reports it without aborting package boot', function (): void {
    Relation::morphMap(['nvl-content-placement' => TestContentOwner::class], false);
    $map = Relation::morphMap();
    $provider = new ContentServiceProvider(app());
    (new ReflectionMethod($provider, 'registerPlacementMorphAlias'))->invoke($provider);
    $diagnostics = app(GlobalNames::class)->diagnostics();
    expect(Relation::morphMap())->toBe($map)
        ->and(collect($diagnostics)->filter(static fn ($check): bool => str_contains($check->message, '[nvl-content-placement]')))->toHaveCount(1);
});
