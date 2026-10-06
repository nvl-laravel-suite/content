<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Nvl\Content\Actions\SyncContentDefinitionsAction;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Facades\Content;
use Nvl\Content\Services\ContentPlacementOwnerLock;
use Nvl\Content\Services\ContentReferenceRegistry;
use Nvl\Content\Tests\Fixtures\TenantScenario;
use Nvl\Content\Tests\Fixtures\UnsafeReferenceResolver;
use Nvl\Support\Tenancy\Contracts\TenantBoundary;
use Nvl\Tenancy\Exceptions\TenantBoundaryViolation;
use Nvl\Tenancy\Exceptions\TenantContextMissing;

beforeEach(function (): void {
    $this->scenario = TenantScenario::install();
});

it('keeps code definitions platform-owned while tenant blocks reuse natural keys', function (): void {
    $create = static fn () => Content::createBlock(new CreateContentBlockData(
        definition: 'hero',
        key: 'homepage',
        scope: 'site',
        scopeKey: 'default',
        translations: ['en' => ['title' => 'Welcome']],
    ), ContentActorData::system());

    $a = $this->scenario->run(TenantScenario::A, $create);
    $b = $this->scenario->run(TenantScenario::B, $create);

    expect($a->tenant_id)->toBe(TenantScenario::A)
        ->and($b->tenant_id)->toBe(TenantScenario::B)
        ->and($a->key)->toBe($b->key);
});

it('fails closed without an admitted tenant', function (): void {
    expect(fn () => Content::createBlock(new CreateContentBlockData(
        definition: 'hero',
        key: 'unresolved',
        scope: 'site',
        scopeKey: 'default',
        translations: ['en' => ['title' => 'Denied']],
    ), ContentActorData::system()))->toThrow(TenantContextMissing::class);
});

it('keeps definition synchronization behind explicit platform execution', function (): void {
    $this->scenario->run(TenantScenario::A, function (): void {
        expect(fn () => app(SyncContentDefinitionsAction::class)->execute(
            ContentActorData::system(),
        ))->toThrow(TenantBoundaryViolation::class);
    });
});

it('keeps tenant placement locks under the owned package prefix without disturbing old host locks', function (): void {
    $this->scenario->run(TenantScenario::A, function (): void {
        $boundary = app(TenantBoundary::class);
        $identity = hash('sha256', "owner-type\0owner-id\0main");
        $foreignKey = $boundary->key('content.placements', 'nvl:content:placement-owner:'.$identity);
        $ownedKey = 'nvl:content:placement-owner:'.$boundary->key('content.placements', $identity);
        $foreignLock = Cache::lock($foreignKey, 10);
        expect($foreignLock->get())->toBeTrue();

        try {
            $result = app(ContentPlacementOwnerLock::class)->run('owner-type', 'owner-id', 'main', static function () use ($ownedKey, $foreignKey): string {
                expect(Cache::lock($ownedKey, 10)->get())->toBeFalse()
                    ->and(Cache::lock($foreignKey, 10)->get())->toBeFalse();

                return 'placed';
            });
            expect($result)->toBe('placed');
        } finally {
            $foreignLock->release();
        }
    });
});

it('rejects tenant-unsafe reference resolvers during registration', function (): void {
    expect(fn () => app(ContentReferenceRegistry::class)->register(
        'unsafe',
        UnsafeReferenceResolver::class,
    ))->toThrow(InvalidArgumentException::class, 'not tenant compatible');
});
