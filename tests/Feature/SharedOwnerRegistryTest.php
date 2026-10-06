<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Tests\Fixtures\TestContentOwner;
use Nvl\Content\Tests\Fixtures\TestIntegerContentOwner;

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
