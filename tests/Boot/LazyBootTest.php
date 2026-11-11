<?php

declare(strict_types=1);

use Nvl\Content\Services\ContentDefinitionRegistry;
use Nvl\Content\Services\ContentDoctor;
use Nvl\Content\Services\ContentModelRegistry;
use Nvl\Content\Tests\Fixtures\LazyBootContentTestCase;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

uses(LazyBootContentTestCase::class);

it('reads no definition file until the definition registry is first used', function (): void {
    expect(LazyBootContentTestCase::$definitionReads)->toBe(0);

    $registry = app(ContentDefinitionRegistry::class);

    expect(LazyBootContentTestCase::$definitionReads)->toBe(0)
        ->and($registry->get('lazy')['name'])->toBe('Lazy')
        ->and(array_column($registry->all(), 'key'))->toBe(['hero', 'lazy'])
        ->and(LazyBootContentTestCase::$definitionReads)->toBe(1);
});

it('keeps reporting an invalid definition source on every use instead of serving a partial registry', function (): void {
    config()->set('nvl-content.definitions', ['broken' => ['name' => 'Broken', 'schema' => ['fields' => [['key' => 'title', 'type' => 'missing-type', 'label' => 'Title']]]]]);
    $registry = app(ContentDefinitionRegistry::class);

    expect(fn () => $registry->all())->toThrow(InvalidArgumentException::class)
        ->and(fn () => $registry->get('lazy'))->toThrow(InvalidArgumentException::class, 'missing-type')
        ->and(app(ContentDoctor::class)->inspect()['definitions.compilation'])->toBeFalse();
});

it('validates the configured block root on first use of the model registry instead of on every boot', function (): void {
    expect(app()->isBooted())->toBeTrue()
        ->and(fn () => ContentModelRegistry::modelClass())->toThrow(OwnershipConfigurationInvalid::class, 'required package casts')
        ->and(fn () => ContentModelRegistry::query())->toThrow(OwnershipConfigurationInvalid::class);
});
