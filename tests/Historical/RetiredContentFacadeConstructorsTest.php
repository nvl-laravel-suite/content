<?php

it('preserves the Content service constructor and appends its optional editor dependency', function (): void {
    $parameters = (new ReflectionClass(ContentEngine::class))->getConstructor()?->getParameters() ?? [];
    expect(array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), array_slice($parameters, 0, 22)))->toBe(['listDefinitions', 'listPresets', 'listBlocks', 'getBlock', 'syncDefinitions', 'planDefinitionMigrations', 'applyDefinitionMigrations', 'listGroups', 'listPlacements', 'createBlock', 'updateBlock', 'publishBlock', 'archiveBlock', 'deleteBlock', 'restoreBlock', 'placeBlock', 'updatePlacement', 'deletePlacement', 'owners', 'renderer', 'snapshots', 'resolveScopes'])->and(array_map(static fn (ReflectionParameter $parameter): bool => $parameter->isOptional(), array_slice($parameters, 22)))->toBe([true]);
});
it('resolves the editor for a Content service built with the legacy constructor', function (): void {
    $reflection = new ReflectionClass(ContentEngine::class);
    $parameters = $reflection->getConstructor()?->getParameters() ?? [];
    $arguments = array_map(static function (ReflectionParameter $parameter): object {
        $type = $parameter->getType();
        expect($type)->toBeInstanceOf(ReflectionNamedType::class);
        assert($type instanceof ReflectionNamedType);

        return app($type->getName());
    }, array_slice($parameters, 0, 22));
    /** @var ContentEngine $content */
    $content = $reflection->newInstanceArgs($arguments);
    $owner = TestContentOwner::query()->create(['name' => 'Legacy Content service owner']);
    $editor = $content->editor($owner, 'homepage', SystemActor::Background);
    expect($editor->ownerType)->toBe(TestContentOwner::class)->and($editor->ownerId)->toBe($owner->id)->and($editor->group)->toBe('homepage')->and($editor->placements)->toBe([]);
});
