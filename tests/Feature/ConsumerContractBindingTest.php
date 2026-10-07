<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Nvl\Content\Actions\ApplyContentDefinitionMigrationsAction;
use Nvl\Content\Actions\ArchiveContentBlockAction;
use Nvl\Content\Actions\CreateContentBlockAction;
use Nvl\Content\Actions\DeleteContentBlockAction;
use Nvl\Content\Actions\DeleteContentPlacementAction;
use Nvl\Content\Actions\ExportContentSnapshotForCopyAction;
use Nvl\Content\Actions\FindContentBlockByKeyAction;
use Nvl\Content\Actions\FindContentPlacementAction;
use Nvl\Content\Actions\GetContentBlockAction;
use Nvl\Content\Actions\GetOwnerContentEditorAction;
use Nvl\Content\Actions\ImportContentSnapshotAction;
use Nvl\Content\Actions\ListContentBlocksAction;
use Nvl\Content\Actions\ListContentDefinitionsAction;
use Nvl\Content\Actions\ListContentGroupsAction;
use Nvl\Content\Actions\ListContentPlacementsAction;
use Nvl\Content\Actions\ListContentPresetsAction;
use Nvl\Content\Actions\ListOwnerContentPlacementSummariesAction;
use Nvl\Content\Actions\PlaceContentBlockAction;
use Nvl\Content\Actions\PlanContentDefinitionMigrationsAction;
use Nvl\Content\Actions\PublishContentBlockAction;
use Nvl\Content\Actions\ReorderContentPlacementsAction;
use Nvl\Content\Actions\ReplaceContentPlacementAction;
use Nvl\Content\Actions\ResolveContentScopesAction;
use Nvl\Content\Actions\RestoreContentBlockAction;
use Nvl\Content\Actions\SyncContentDefinitionsAction;
use Nvl\Content\Actions\UpdateContentBlockAction;
use Nvl\Content\Actions\UpdateContentPlacementAction;
use Nvl\Content\Content;
use Nvl\Content\Contracts\ApplyContentDefinitionMigrationsContract;
use Nvl\Content\Contracts\ArchiveContentBlockContract;
use Nvl\Content\Contracts\ContentContract;
use Nvl\Content\Contracts\ContentOwnerRegistrar;
use Nvl\Content\Contracts\CreateContentBlockContract;
use Nvl\Content\Contracts\DeleteContentBlockContract;
use Nvl\Content\Contracts\DeleteContentPlacementContract;
use Nvl\Content\Contracts\ExportContentSnapshotForCopyContract;
use Nvl\Content\Contracts\FindContentBlockByKeyContract;
use Nvl\Content\Contracts\FindContentPlacementContract;
use Nvl\Content\Contracts\GetContentBlockContract;
use Nvl\Content\Contracts\GetOwnerContentEditorContract;
use Nvl\Content\Contracts\ImportContentSnapshotContract;
use Nvl\Content\Contracts\ListContentBlocksContract;
use Nvl\Content\Contracts\ListContentDefinitionsContract;
use Nvl\Content\Contracts\ListContentGroupsContract;
use Nvl\Content\Contracts\ListContentPlacementsContract;
use Nvl\Content\Contracts\ListContentPresetsContract;
use Nvl\Content\Contracts\ListOwnerContentPlacementSummariesContract;
use Nvl\Content\Contracts\PlaceContentBlockContract;
use Nvl\Content\Contracts\PlanContentDefinitionMigrationsContract;
use Nvl\Content\Contracts\PublishContentBlockContract;
use Nvl\Content\Contracts\ReorderContentPlacementsContract;
use Nvl\Content\Contracts\ReplaceContentPlacementContract;
use Nvl\Content\Contracts\ResolveContentScopesContract;
use Nvl\Content\Contracts\RestoreContentBlockContract;
use Nvl\Content\Contracts\SyncContentDefinitionsContract;
use Nvl\Content\Contracts\UpdateContentBlockContract;
use Nvl\Content\Contracts\UpdateContentPlacementContract;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Content\Tests\TestCase;

if (! in_array(dirname(__DIR__).'/Pest.php', get_included_files(), true)) {
    uses(TestCase::class);
}

/** @return list<array{class-string, class-string}> */
function nvlConsumerBindingsForContent(): array
{
    return [
        [ApplyContentDefinitionMigrationsContract::class, ApplyContentDefinitionMigrationsAction::class],
        [ArchiveContentBlockContract::class, ArchiveContentBlockAction::class],
        [ContentContract::class, Content::class],
        [CreateContentBlockContract::class, CreateContentBlockAction::class],
        [DeleteContentBlockContract::class, DeleteContentBlockAction::class],
        [DeleteContentPlacementContract::class, DeleteContentPlacementAction::class],
        [ExportContentSnapshotForCopyContract::class, ExportContentSnapshotForCopyAction::class],
        [FindContentBlockByKeyContract::class, FindContentBlockByKeyAction::class],
        [FindContentPlacementContract::class, FindContentPlacementAction::class],
        [GetContentBlockContract::class, GetContentBlockAction::class],
        [GetOwnerContentEditorContract::class, GetOwnerContentEditorAction::class],
        [ImportContentSnapshotContract::class, ImportContentSnapshotAction::class],
        [ListContentBlocksContract::class, ListContentBlocksAction::class],
        [ListContentDefinitionsContract::class, ListContentDefinitionsAction::class],
        [ListContentGroupsContract::class, ListContentGroupsAction::class],
        [ListContentPlacementsContract::class, ListContentPlacementsAction::class],
        [ListContentPresetsContract::class, ListContentPresetsAction::class],
        [ListOwnerContentPlacementSummariesContract::class, ListOwnerContentPlacementSummariesAction::class],
        [PlaceContentBlockContract::class, PlaceContentBlockAction::class],
        [PlanContentDefinitionMigrationsContract::class, PlanContentDefinitionMigrationsAction::class],
        [PublishContentBlockContract::class, PublishContentBlockAction::class],
        [ReorderContentPlacementsContract::class, ReorderContentPlacementsAction::class],
        [ReplaceContentPlacementContract::class, ReplaceContentPlacementAction::class],
        [ResolveContentScopesContract::class, ResolveContentScopesAction::class],
        [RestoreContentBlockContract::class, RestoreContentBlockAction::class],
        [SyncContentDefinitionsContract::class, SyncContentDefinitionsAction::class],
        [UpdateContentBlockContract::class, UpdateContentBlockAction::class],
        [UpdateContentPlacementContract::class, UpdateContentPlacementAction::class],
    ];
}

test('published workflow contracts retain native signatures attributes and generic documentation', function (): void {
    $genericDocumentation = static function (string|false $documentation): array {
        if ($documentation === false) {
            return [];
        }
        preg_match_all('/@param\s+([^\r\n]+?)\s+(\$[A-Za-z_][A-Za-z0-9_]*)\b/', $documentation, $parameters, PREG_SET_ORDER);
        $result = [];
        foreach ($parameters as $parameter) {
            $type = preg_replace('/\s+/', '', $parameter[1]);
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@param'.$parameter[2]] = $type;
            }
        }
        if (preg_match('/@return\s+([^\r\n]+)/', $documentation, $return) === 1) {
            $type = '';
            $depth = 0;
            foreach (str_split($return[1]) as $character) {
                if (preg_match('/\s/', $character) === 1 && $depth === 0) {
                    break;
                }
                if (str_contains('<{([', $character)) {
                    $depth++;
                } elseif (str_contains('>})]', $character)) {
                    $depth--;
                }
                if (preg_match('/\s/', $character) !== 1) {
                    $type .= $character;
                }
            }
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@return'] = $type;
            }
        }

        return $result;
    };

    foreach (nvlConsumerBindingsForContent() as [$contract, $implementation]) {
        $interface = new ReflectionClass($contract);
        $concrete = new ReflectionClass($implementation);
        expect($interface->isInterface())->toBeTrue()
            ->and($concrete->implementsInterface($contract))->toBeTrue();
        foreach ($interface->getMethods() as $method) {
            $native = $concrete->getMethod($method->getName());
            $return = (string) $method->getReturnType();

            $publishedTypes = $genericDocumentation($method->getDocComment());
            foreach ($genericDocumentation($native->getDocComment()) as $tag => $type) {
                expect($publishedTypes[$tag] ?? null)->toBe($type);
            }

            expect($native->isPublic())->toBeTrue()
                ->and($native->isStatic())->toBeFalse()
                ->and(count($method->getParameters()))->toBe(count($native->getParameters()));
            if ($return !== 'self') {
                expect((string) $native->getReturnType())->toBe($return);
            } else {
                $nativeReturn = (string) $native->getReturnType();
                expect(is_a(in_array($nativeReturn, ['self', 'static'], true) ? $native->getDeclaringClass()->getName() : $nativeReturn, $contract, true))->toBeTrue();
            }
            foreach ($method->getParameters() as $position => $parameter) {
                $actual = $native->getParameters()[$position];
                expect($actual->getName())->toBe($parameter->getName())
                    ->and((string) $actual->getType())->toBe((string) $parameter->getType())
                    ->and($actual->isVariadic())->toBe($parameter->isVariadic())
                    ->and($actual->isPassedByReference())->toBe($parameter->isPassedByReference())
                    ->and($actual->isDefaultValueAvailable())->toBe($parameter->isDefaultValueAvailable())
                    ->and(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $actual->getAttributes()))
                    ->toBe(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $parameter->getAttributes()));
                if ($parameter->isDefaultValueAvailable()) {
                    expect($actual->getDefaultValue())->toEqual($parameter->getDefaultValue());
                }
            }
        }
    }
});

test('native provider defaults resolve each workflow while preserving late host substitutes', function (): void {
    foreach (nvlConsumerBindingsForContent() as [$contract, $implementation]) {
        expect($this->app->bound($contract))->toBeTrue()
            ->and($this->app->make($contract))->toBeInstanceOf($implementation);
        $host = Mockery::mock($contract);
        $this->app->instance($contract, $host);
        expect($this->app->make($contract))->toBe($host);
    }
});

test('provider registration preserves early interface bindings in a second native application', function (): void {
    $consumer = new Application($this->app->basePath());
    $consumer->instance('config', new Repository($this->app->make('config')->all()));
    $consumer->instance('env', 'testing');
    $consumer->register(FilesystemServiceProvider::class);
    $hosts = [];
    foreach (nvlConsumerBindingsForContent() as [$contract]) {
        $hosts[$contract] = Mockery::mock($contract);
        $consumer->instance($contract, $hosts[$contract]);
    }
    try {
        $consumer->register(ContentServiceProvider::class);
        foreach ($hosts as $contract => $host) {
            expect($consumer->make($contract))->toBe($host);
        }
    } finally {
        Container::setInstance($this->app);
        $consumer->flush();
    }
});

test('preserves a host Content owner registrar and records its container collision', function (): void {
    $consumer = new Application($this->app->basePath());
    $consumer->instance('config', new Repository($this->app->make('config')->all()));
    $consumer->instance('env', 'testing');
    $consumer->register(FilesystemServiceProvider::class);
    $host = Mockery::mock(ContentOwnerRegistrar::class);
    $consumer->instance(ContentOwnerRegistrar::class, $host);
    try {
        $consumer->register(ContentServiceProvider::class);
        expect($consumer->make(ContentOwnerRegistrar::class))->toBe($host)
            ->and($consumer['config']->get('nvl-core.configuration.global_names'))->not->toBeEmpty();
    } finally {
        Container::setInstance($this->app);
        $consumer->flush();
    }
});
