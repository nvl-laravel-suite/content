<?php

declare(strict_types=1);
use Nvl\Content\Actions\SyncContentDefinitionsAction;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Models\ContentDefinition;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Tests\Fixtures\OwnershipActor;
use Nvl\Content\Tests\Fixtures\OwnershipCompany;
use Nvl\Content\Tests\Fixtures\OwnershipContentScenario;
use Nvl\Content\Tests\OwnershipPrivateCatalogTestCase;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

uses(OwnershipPrivateCatalogTestCase::class);
it('synchronizes global code metadata without granting access to private mutable Content roots', function (): void {
    $runner = app(OwnershipRunner::class);
    $actor = OwnershipActor::firstOrFail();
    $runner->run(OwnershipCompany::findOrFail(7), $actor, fn () => OwnershipContentScenario::block());
    $runner->central('content-definitions', $actor, function (): void {
        ContentDefinition::query()->where('key', 'copy')->update(['version' => 0, 'source_hash' => str_repeat('0', 64)]);
        app(SyncContentDefinitionsAction::class)->execute(ContentActorData::system());
        expect(ContentDefinition::query()->where('key', 'copy')->value('version'))->toBe(1);
        expect(fn () => ContentModelRegistry::query()->get())->toThrow(OwnershipViolation::class);
    });
});
