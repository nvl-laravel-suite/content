<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\FieldTypes\StringFieldTypeAdapter;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentPlacements;
use Nvl\Content\Services\ContentReader;
use Nvl\Content\Services\ContentRenderer;
use Nvl\Content\Tests\Fixtures\TestContentOwner;
use Nvl\Content\Validation\ContentValidationContext;
use Nvl\Media\Enums\MediaLifecycleStatus;
use Nvl\Media\Enums\MediaType;
use Nvl\Media\Enums\MediaVisibility;
use Nvl\Media\Models\Media;
use Nvl\Support\Enums\SystemActor;

beforeEach(function (): void {
    Storage::fake('public');
    app(ContentDefinitions::class)->sync(SystemActor::Background);
});

it('never signs private media into a public-only render requested by a privileged actor', function (): void {
    $actor = SystemActor::Background;
    $media = Media::factory()->create(['mime_type' => 'image/jpeg', 'extension' => 'jpg', 'type' => MediaType::IMAGE, 'is_public' => true, 'visibility' => MediaVisibility::Public, 'status' => MediaLifecycleStatus::Available]);
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'withdrawn-image', 'scope' => 'site', 'scopeKey' => 'main-site', 'visibility' => ContentVisibility::Public, 'values' => ['image' => $media->id], 'translations' => ['en' => ['title' => 'Public']], 'metadata' => []], $actor);
    $block = app(ContentBlocks::class)->publish($block, $block->revision, $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Public page']);
    app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'hero', 'region' => 'main', 'parentId' => null, 'sortOrder' => 0, 'isVisible' => true, 'overrides' => []], $actor);
    Media::query()->whereKey($media->id)->update(['visibility' => MediaVisibility::Private->value, 'is_public' => false]);

    $public = app(ContentRenderer::class)->render($owner, ['group' => 'default', 'locale' => 'en', 'actor' => $actor, 'publicOnly' => true]);
    $private = app(ContentRenderer::class)->render($owner, ['group' => 'default', 'locale' => 'en', 'actor' => $actor, 'publicOnly' => false]);

    expect(contentRenderedValue($public, 'hero.title'))->toBe('Public')
        ->and(contentRenderedValue($public, 'hero.image'))->toBeNull()
        ->and(contentRenderedValue($private, 'hero.image'))->toBeArray();
});

it('rejects a hex color that ends with a line break', function (): void {
    $context = new ContentValidationContext(actor: SystemActor::Background, locale: 'en', path: 'accent', visibility: ContentVisibility::Public);
    $adapter = new StringFieldTypeAdapter('color');
    $field = new ContentFieldDefinition('accent', 'color', 'Accent');

    expect($adapter->normalize('#ffcc00', $field, $context))->toBe('#ffcc00')
        ->and(fn () => $adapter->normalize("#ffcc00\n", $field, $context))->toThrow(InvalidArgumentException::class);
});

it('rejects block and placement identities that end with a line break', function (): void {
    $actor = SystemActor::Background;
    $blocks = app(ContentBlocks::class);
    $block = $blocks->create(['definition' => 'hero', 'key' => 'line', 'scope' => 'site', 'scopeKey' => 'main-site', 'translations' => ['en' => ['title' => 'Line']]], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Line page']);

    expect(fn () => $blocks->create(['definition' => 'hero', 'key' => "line-break\n", 'scope' => 'site', 'scopeKey' => 'main-site', 'translations' => ['en' => ['title' => 'Line']]], $actor))->toThrow(ValidationException::class)
        ->and(fn () => app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => "hero\n"], $actor))->toThrow(ValidationException::class)
        ->and(fn () => app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'hero', 'region' => "main\n"], $actor))->toThrow(ValidationException::class);
});

it('refuses numeric strings for revisions and sort orders instead of failing with a type error or a false conflict', function (): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'typed', 'scope' => 'site', 'scopeKey' => 'main-site', 'translations' => ['en' => ['title' => 'Typed']]], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Typed page']);
    $placements = app(ContentPlacements::class);
    $placement = $placements->place($block, $owner, 'default', ['key' => 'typed'], $actor);

    expect(fn () => app(ContentBlocks::class)->update($block, ['expected_revision' => (string) $block->revision, 'translations' => ['en' => ['title' => 'Changed']]], $actor))->toThrow(ValidationException::class)
        ->and(fn () => $placements->place($block, $owner, 'default', ['key' => 'second', 'sortOrder' => '1'], $actor))->toThrow(ValidationException::class)
        ->and(fn () => $placements->update($placement, ['expected_revision' => $placement->revision, 'region' => 'main', 'sortOrder' => '2', 'isVisible' => true], $actor))->toThrow(ValidationException::class)
        ->and($block->refresh()->revision)->toBe(1);
});

it('refuses a malformed reorder proposal as invalid input', function (array $item): void {
    $actor = SystemActor::Background;
    $block = app(ContentBlocks::class)->create(['definition' => 'hero', 'key' => 'reorder', 'scope' => 'site', 'scopeKey' => 'main-site', 'translations' => ['en' => ['title' => 'Reorder']]], $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Reorder page']);
    $placement = app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'reorder'], $actor);
    $valid = ['id' => $placement->id, 'expected_revision' => $placement->revision, 'region' => 'main', 'sortOrder' => 0];

    expect(fn () => app(ContentPlacements::class)->move($owner, 'default', ['placements' => [array_filter([...$valid, ...$item], static fn (mixed $value): bool => $value !== '__missing__')]], $actor))->toThrow(ValidationException::class)
        ->and(app(ContentPlacements::class)->move($owner, 'default', ['placements' => [$valid]], $actor))->toHaveCount(1);
})->with([
    'missing id' => [['id' => '__missing__']],
    'missing revision' => [['expected_revision' => '__missing__']],
    'numeric region' => [['region' => 7]],
    'string order' => [['sortOrder' => '1']],
    'array parent' => [['parentId' => ['x']]],
]);

it('refuses a reorder item that is not a record', function (): void {
    $owner = TestContentOwner::query()->create(['name' => 'Scalar page']);

    expect(fn () => app(ContentPlacements::class)->move($owner, 'default', ['placements' => ['not-a-record']], SystemActor::Background))->toThrow(ValidationException::class);
});

it('reports a reused block or placement key as invalid input instead of a database error', function (): void {
    $actor = SystemActor::Background;
    $blocks = app(ContentBlocks::class);
    $input = ['definition' => 'hero', 'key' => 'reused', 'scope' => 'site', 'scopeKey' => 'main-site', 'translations' => ['en' => ['title' => 'Reused']]];
    $block = $blocks->create($input, $actor);
    $owner = TestContentOwner::query()->create(['name' => 'Reused page']);
    app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'slot'], $actor);

    expect(fn () => $blocks->create($input, $actor))->toThrow(InvalidArgumentException::class, 'already exists')
        ->and(fn () => app(ContentPlacements::class)->place($block, $owner, 'default', ['key' => 'slot'], $actor))->toThrow(InvalidArgumentException::class, 'already exists')
        ->and(ContentPlacement::query()->count())->toBe(1);
});

it('treats a malformed block or placement identifier as not found', function (): void {
    $actor = SystemActor::Background;
    $owner = TestContentOwner::query()->create(['name' => 'Missing page']);

    expect(fn () => app(ContentBlocks::class)->publish('not-a-uuid', 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentBlocks::class)->update('not-a-uuid', ['expected_revision' => 1], $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentBlocks::class)->archive('not-a-uuid', 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentBlocks::class)->restore('not-a-uuid', 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentBlocks::class)->delete('not-a-uuid', 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentReader::class)->get('not-a-uuid', $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentPlacements::class)->place('not-a-uuid', $owner, 'default', ['key' => 'slot'], $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentPlacements::class)->update('not-a-uuid', ['expected_revision' => 1, 'region' => 'main', 'sortOrder' => 0, 'isVisible' => true], $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentPlacements::class)->delete('not-a-uuid', 1, $actor))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ContentReader::class)->placement($owner, 'default', 'not-a-uuid', $actor))->toThrow(ModelNotFoundException::class);
});
