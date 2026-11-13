<?php

declare(strict_types=1);
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentBlockTranslation;
use Nvl\Content\Models\ContentDefinition;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Models\ContentRevision;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDoctor;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Tests\Fixtures\OwnershipActor;
use Nvl\Content\Tests\Fixtures\OwnershipCompany;
use Nvl\Content\Tests\Fixtures\OwnershipContentBlock;
use Nvl\Content\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Content\Tests\Fixtures\UnsafeQueryContentBlock;
use Nvl\Content\Tests\OwnershipTestCase;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Tests\Fixtures\OptsIntoAdoptedSchemaReuse;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

uses(OwnershipTestCase::class, OptsIntoAdoptedSchemaReuse::class);
it('uses the configured block root and canonical dependent ownership', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $block = app(ContentBlocks::class)->create(['definition' => 'copy', 'key' => 'copy', 'translations' => ['en' => ['text' => 'Private copy']]], SystemActor::Background);
        expect($block::class)->toBe(OwnershipContentBlock::class)->and(ContentModelRegistry::query()->count())->toBe(1)->and($block->translations->first()->block::class)->toBe(OwnershipContentBlock::class);
    });
});

it('creates related factory records through the configured owned block root', function (): void {
    app(OwnershipRunner::class)->run(OwnershipCompany::findOrFail(7), OwnershipActor::firstOrFail(), function (): void {
        $definition = ContentDefinition::query()->where('key', 'copy')->firstOrFail();
        $block = app(OwnershipWriter::class)->create(ContentModelRegistry::modelClass(), fn (array $attributes) => ContentBlock::factory()->forDefinition($definition)->state($attributes)->create());
        $owner = app(OwnershipWriter::class)->create(OwnershipContentOwner::class, fn (array $attributes) => OwnershipContentOwner::create([...$attributes, 'name' => 'Factory owner']));
        $translation = ContentBlockTranslation::factory()->forBlock($block)->create();
        $revision = ContentRevision::factory()->forBlock($block)->create();
        $placement = ContentPlacement::factory()->forOwner($owner)->forBlock($block)->state(['group' => 'main'])->create();

        expect($block)->toBeInstanceOf(OwnershipContentBlock::class)
            ->and($translation->block)->toBeInstanceOf(OwnershipContentBlock::class)
            ->and($revision->block)->toBeInstanceOf(OwnershipContentBlock::class)
            ->and($placement->block)->toBeInstanceOf(OwnershipContentBlock::class)
            ->and($translation->block->company_id)->toBe(7)
            ->and($revision->snapshot['key'])->toBe($block->key);
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
