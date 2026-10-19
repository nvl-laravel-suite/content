<?php

declare(strict_types=1);
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Actions\CreateContentBlockAction;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Services\ContentDoctor;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Tests\Fixtures\OwnershipActor;
use Nvl\Content\Tests\Fixtures\OwnershipCompany;
use Nvl\Content\Tests\Fixtures\OwnershipContentBlock;
use Nvl\Content\Tests\Fixtures\UnsafeQueryContentBlock;
use Nvl\Content\Tests\OwnershipTestCase;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

uses(OwnershipTestCase::class);
it('uses the configured block root and canonical dependent ownership', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = app(CreateContentBlockAction::class)->execute(CreateContentBlockData::from(['definition' => 'copy', 'key' => 'copy', 'translations' => ['en' => ['text' => 'Private copy']]]), ContentActorData::system());
        expect($block::class)->toBe(OwnershipContentBlock::class)->and(ContentModelRegistry::query()->count())->toBe(1)
            ->and($block->translations->first()->block::class)->toBe(OwnershipContentBlock::class);
    });
});

it('rejects configured subclasses that remove native query admission', function (): void {
    $map = Relation::morphMap();
    Relation::morphMap([], false);
    try {
        $configuration = new Repository(['nvl-content' => ['models' => ['block' => UnsafeQueryContentBlock::class]]]);
        $registry = new ContentModelRegistry($configuration, new OwnerRegistry($configuration));
        expect(fn () => $registry->blockClass())->toThrow(OwnershipConfigurationInvalid::class);
    } finally {
        Relation::morphMap($map, false);
    }
});
it('diagnoses native Content indexes rather than removed global identities', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $checks = app(ContentDoctor::class)->inspect();
        expect($checks['schema.ownership'] ?? null)->toBeTrue()->and($checks['schema.indexes'])->toBeTrue();
    });
});
