<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nvl\Content\Actions\SyncContentDefinitionsAction;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\Mutations\CreateContentBlockData;
use Nvl\Content\Data\Mutations\PlaceContentBlockData;
use Nvl\Content\Facades\Content;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentDefinition;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Tenancy\ContentTenantParentResolver;
use Nvl\Content\Tests\Fixtures\TenantContentOwner;
use Nvl\Content\Tests\Fixtures\TenantScenario;
use Nvl\Content\Tests\MappedOwnerTenancyTestCase;
use Nvl\Support\Tenancy\Contracts\TenantBoundary;
use Nvl\Tenancy\Exceptions\TenantBoundaryViolation;
use Nvl\Tenancy\Services\TenantAdoptionCoordinator;
use Nvl\Tenancy\Services\TenantRunner;
use Nvl\Tenancy\ValueObjects\PlatformOperation;
use Nvl\Tenancy\ValueObjects\TenantAssignment;
use Nvl\Tenancy\ValueObjects\TenantId;

uses(MappedOwnerTenancyTestCase::class);

it('queries and checks mapped class-list Content placement owners through their native identity', function (): void {
    $scenario = TenantScenario::install();
    $owner = $scenario->owner($scenario::A);
    $placement = $scenario->run($scenario::A, static function () use ($owner): ContentPlacement {
        $actor = ContentActorData::system();
        $block = Content::createBlock(new CreateContentBlockData(
            definition: 'hero', key: 'mapped', scope: 'site', scopeKey: 'default',
            translations: ['en' => ['title' => 'Mapped owner']],
        ), $actor);

        return Content::place($block, $owner, 'default', new PlaceContentBlockData('hero'), $actor);
    });

    expect(app(ContentTenantParentResolver::class)->types())->toBe(['host.content-owner' => TenantContentOwner::class])
        ->and($placement->owner_type)->toBe('host.content-owner')
        ->and($scenario->run($scenario::A, static fn (): bool => ContentPlacement::query()->whereKey($placement->id)->exists()))->toBeTrue()
        ->and($scenario->run($scenario::B, static fn (): bool => ContentPlacement::query()->whereKey($placement->id)->exists()))->toBeFalse()
        ->and(fn () => $scenario->run($scenario::B, static fn () => app(TenantBoundary::class)->assertRecord($placement, 'content.placements')))
        ->toThrow(TenantBoundaryViolation::class)
        ->and(Relation::getMorphedModel('host.content-owner'))->toBe(TenantContentOwner::class);
});

it('adopts an existing native mapped placement and preserves its host owner identity', function (): void {
    app(TenantRunner::class)->platform(
        new PlatformOperation('content.legacy.definition', 'test', 'pest'),
        static fn () => app(SyncContentDefinitionsAction::class)->execute(ContentActorData::system()),
    );
    $definition = ContentDefinition::query()->where('key', 'hero')->firstOrFail();
    $blockId = (string) Str::uuid();
    $ownerId = (string) Str::uuid();
    $placementId = (string) Str::uuid();
    $coordinator = app(TenantAdoptionCoordinator::class);
    $operation = new PlatformOperation('content.fixture.adoption', 'test', 'pest');
    $plan = $coordinator->prepare(['media', 'content-test-owners', 'content'], [
        new TenantAssignment('content.blocks', $blockId, new TenantId(TenantScenario::A)),
    ], $operation);
    DB::table((new TenantContentOwner)->getTable())->insert([
        'id' => $ownerId, 'tenant_id' => TenantScenario::A, 'name' => 'Existing owner',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table((new ContentBlock)->getTable())->insert([
        'id' => $blockId, 'definition_id' => $definition->id, 'key' => 'existing',
        'scope' => 'site', 'scope_key' => 'default', 'status' => 'draft', 'visibility' => 'public',
        'values' => '[]', 'metadata' => '[]', 'definition_version' => $definition->version,
        'definition_hash' => $definition->source_hash, 'definition_schema' => json_encode($definition->schema, JSON_THROW_ON_ERROR),
        'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table((new ContentPlacement)->getTable())->insert([
        'id' => $placementId, 'content_block_id' => $blockId, 'owner_type' => 'host.content-owner',
        'owner_id' => $ownerId, 'group' => 'default', 'key' => 'hero',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $done = false;
    for ($batch = 0; $batch < 20 && ! $done; $batch++) {
        $done = $coordinator->backfill($plan, 100, $operation);
    }
    expect($done)->toBeTrue()->and($coordinator->verify($plan)->passed())->toBeTrue();
    $coordinator->activate($plan, $operation);
    app(MaintenanceMode::class)->deactivate();
    $scenario = new TenantScenario;
    $placement = $scenario->run($scenario::A, static fn () => ContentPlacement::query()->findOrFail($placementId));
    expect($placement->tenant_id)->toBe($scenario::A)
        ->and($placement->owner_type)->toBe('host.content-owner')
        ->and($scenario->run($scenario::B, static fn (): bool => ContentPlacement::query()->whereKey($placementId)->exists()))->toBeFalse();
});
