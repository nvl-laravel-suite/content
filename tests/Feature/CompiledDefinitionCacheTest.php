<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Nvl\Content\Exceptions\ContentDefinitionCacheException;
use Nvl\Content\FieldPresets\ConfiguredContentFieldPreset;
use Nvl\Content\Services\CanonicalJson;
use Nvl\Content\Services\CompiledContentDefinitionCache;
use Nvl\Content\Services\ContentCompilationSignature;
use Nvl\Content\Services\ContentDefinitionRegistry;
use Nvl\Content\Services\ContentFieldPresetRegistry;
use Nvl\Content\Services\ContentPresetDiagnostics;

beforeEach(function (): void {
    $this->cacheDirectory = base_path('bootstrap/cache/content-acceptance-'.bin2hex(random_bytes(8)));
    mkdir($this->cacheDirectory, 0755, true);
    $this->cachePath = $this->cacheDirectory.'/definitions.json';
    config()->set('nvl-content.compiled_cache.path', $this->cachePath);
    config()->set('nvl-content.compiled_cache.version', 'release-1');
    $this->cache = app(CompiledContentDefinitionCache::class);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->cacheDirectory);
});

it('restores the same compiled definitions without reading or executing source files', function (): void {
    $source = $this->cacheDirectory.'/source.content.php';
    file_put_contents($source, '<?php return [];');
    config()->set('nvl-content.definition_paths', [$source]);
    config()->set('nvl-content.allowed_definition_roots', [$this->cacheDirectory]);
    $this->cache->write();
    $expected = array_map(fn ($definition) => $definition->toArray(), app(ContentDefinitionRegistry::class)->all());
    file_put_contents($source, '<?php throw new RuntimeException("Source executed during warm restore");');
    $restored = app()->build(ContentDefinitionRegistry::class);
    $this->cache->restore($restored);
    expect(app(CanonicalJson::class)->encode(array_map(fn ($definition) => $definition->toArray(), $restored->all())))->toBe(app(CanonicalJson::class)->encode($expected))
        ->and($this->cache->inspect()['stale'])->toBeTrue();
});

it('rejects tampered envelope identities even when a new digest is supplied', function (string $field, mixed $value): void {
    $this->cache->write();
    $payload = json_decode(file_get_contents($this->cachePath), true, flags: JSON_THROW_ON_ERROR);
    unset($payload['payload_sha256']);
    $payload[$field] = $value;
    $payload['payload_sha256'] = app(CanonicalJson::class)->hash($payload);
    file_put_contents($this->cachePath, json_encode($payload, JSON_THROW_ON_ERROR));
    expect(fn () => $this->cache->restore(app()->build(ContentDefinitionRegistry::class)))->toThrow(ContentDefinitionCacheException::class);
})->with(['ABI' => ['compiler_abi', 'unknown'], 'schema' => ['schema_version', 2], 'revision' => ['package_reference', 'old'], 'signature' => ['signature', 'old'], 'version' => ['version', 'release-0'], 'source traversal' => ['sources', ['root-0/../secret' => str_repeat('a', 64)]]]);

it('rejects digest corruption and invalid definition bounds before mutating the registry', function (): void {
    $this->cache->write();
    $payload = json_decode(file_get_contents($this->cachePath), true, flags: JSON_THROW_ON_ERROR);
    $payload['definitions'][0]['name'] = 'tampered';
    file_put_contents($this->cachePath, json_encode($payload, JSON_THROW_ON_ERROR));
    $registry = app()->build(ContentDefinitionRegistry::class);
    expect(fn () => $this->cache->restore($registry))->toThrow(ContentDefinitionCacheException::class)
        ->and($registry->all())->toBe([]);
    unset($payload['payload_sha256']);
    $payload['definitions'] = array_fill(0, 501, $payload['definitions'][0]);
    $payload['payload_sha256'] = app(CanonicalJson::class)->hash($payload);
    file_put_contents($this->cachePath, json_encode($payload, JSON_THROW_ON_ERROR));
    expect(fn () => $this->cache->restore($registry))->toThrow(ContentDefinitionCacheException::class)
        ->and($registry->all())->toBe([]);
});

it('preserves the previous artifact when recompilation fails and rejects symlink paths', function (): void {
    $this->cache->write();
    $previous = file_get_contents($this->cachePath);
    config()->set('nvl-content.definitions', ['invalid' => ['version' => 0]]);
    expect(fn () => $this->cache->write())->toThrow(InvalidArgumentException::class)
        ->and(file_get_contents($this->cachePath))->toBe($previous);
    $linked = $this->cacheDirectory.'/linked.json';
    symlink($this->cachePath, $linked);
    config()->set('nvl-content.compiled_cache.path', $linked);
    expect(fn () => $this->cache->clear())->toThrow(ContentDefinitionCacheException::class)
        ->and(file_get_contents($this->cachePath))->toBe($previous);
});

it('invalidates effective compiler inputs and requires an explicit required-mode token', function (): void {
    $signature = app(ContentCompilationSignature::class);
    $before = $signature->current();
    config()->set('nvl-content.locales.required', ['bg']);
    expect($signature->current())->not->toBe($before);
    config()->set('nvl-content.compiled_cache.required', true);
    config()->set('nvl-content.compiled_cache.version', null);
    expect(fn () => $this->cache->write())->toThrow(ContentDefinitionCacheException::class, 'explicit deployment version');
});

it('diagnoses every unused invalid preset and continues to valid presets', function (): void {
    $presets = app(ContentFieldPresetRegistry::class);
    $presets->register(new ConfiguredContentFieldPreset('unused_invalid', 'Invalid', null, ['type' => 'unknown_type']));
    $presets->register(new ConfiguredContentFieldPreset('unused_valid', 'Valid', null, ['type' => 'text']));
    $checks = collect(app(ContentPresetDiagnostics::class)->inspect())->keyBy('key');
    expect($checks['presets.unused_invalid.schema']->passed)->toBeFalse()
        ->and($checks['presets.unused_valid.schema']->passed)->toBeTrue();
});

it('rejects oversized artifacts and can clear a stale artifact without compiling its sources', function (): void {
    file_put_contents($this->cachePath, str_repeat('x', 16_777_217));
    expect(fn () => $this->cache->restore(app()->build(ContentDefinitionRegistry::class)))->toThrow(ContentDefinitionCacheException::class, '16 MiB');
    config()->set('nvl-content.definitions', 'uncompilable');
    $this->cache->clear();
    expect(file_exists($this->cachePath))->toBeFalse();
});

it('uses the nonreserved cache version option and preserves the configured deployment token', function (): void {
    $this->artisan('nvl:content:cache', ['--cache-version' => 'release-1'])->assertSuccessful();
    $payload = json_decode(file_get_contents($this->cachePath), true, flags: JSON_THROW_ON_ERROR);
    expect($payload['version'])->toBe('release-1')
        ->and(config('nvl-content.compiled_cache.version'))->toBe('release-1');
    expect(fn () => Artisan::call('nvl:content:cache', ['--cache-version' => 'wrong-release']))->toThrow(ContentDefinitionCacheException::class, 'match configuration');
    expect(file_get_contents($this->cachePath))->toBe(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
});
