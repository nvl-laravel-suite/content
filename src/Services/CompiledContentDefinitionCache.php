<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use JsonException;
use Nvl\Content\Data\ContentDefinitionData;
use Nvl\Content\Exceptions\ContentDefinitionCacheException;
use Nvl\Content\Support\ContentArrays;
use Throwable;

/** Publishes and restores bounded scalar compiled definitions with explicit deployment identity. @internal */
final readonly class CompiledContentDefinitionCache
{
    private const int MAXIMUM_BYTES = 16_777_216;

    /** Retain filesystem policy, compiler inputs and the isolated source compiler. */
    public function __construct(
        private Application $application,
        private Repository $configuration,
        private ContentDefinitionLoader $loader,
        private ContentDefinitionCompilation $compilation,
        private ContentCompilationSignature $signature,
        private CanonicalJson $json,
        private ContentPayloadGuard $guard,
    ) {}

    /** Compile source once and atomically replace the cache only after complete validation. */
    public function write(?string $version = null): void
    {
        $version = $this->version($version);
        $sources = $this->loader->sourceManifest();
        $definitions = array_map(static fn (ContentDefinitionData $definition): array => $definition->toArray(), $this->compilation->compile());
        if ($sources !== $this->loader->sourceManifest()) {
            throw ContentDefinitionCacheException::invalid('definition sources changed during compilation');
        }
        $payload = [
            'schema_version' => 1,
            'compiler_abi' => ContentCompilationSignature::ABI,
            'package_reference' => $this->packageReference(),
            'version' => $version,
            'signature' => $this->signature->current(),
            'sources' => $sources,
            'definitions' => $definitions,
        ];
        $this->guard->json($payload, 'Compiled Content cache', self::MAXIMUM_BYTES, 64);
        $payload['payload_sha256'] = $this->json->hash($payload);
        $encoded = $this->json->encode($payload);
        if (strlen($encoded) > self::MAXIMUM_BYTES) {
            throw ContentDefinitionCacheException::invalid('the cache exceeds 16 MiB');
        }

        $path = $this->path();
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ContentDefinitionCacheException::invalid('the cache directory cannot be created');
        }
        $this->assertSafePath($path);
        $lockPath = $path.'.lock';
        $this->assertSafePath($lockPath);
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw ContentDefinitionCacheException::invalid('the publication lock cannot be opened');
        }
        $temporary = null;
        try {
            if (! flock($lock, LOCK_EX)) {
                throw ContentDefinitionCacheException::invalid('the publication lock cannot be acquired');
            }
            $this->assertSafePath($path);
            $temporary = tempnam($directory, '.nvl-content-');
            if ($temporary === false || dirname($temporary) !== realpath($directory)) {
                throw ContentDefinitionCacheException::invalid('a same-directory temporary file cannot be created');
            }
            if (file_put_contents($temporary, $encoded, LOCK_EX) !== strlen($encoded)) {
                throw ContentDefinitionCacheException::invalid('the temporary cache cannot be written completely');
            }
            $this->assertSafePath($path);
            if (! rename($temporary, $path)) {
                throw ContentDefinitionCacheException::invalid('the cache cannot be published atomically');
            }
            $temporary = null;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Restore a matching compiled set without discovering or reading definition sources. */
    public function restore(ContentDefinitionRegistry $registry): void
    {
        try {
            $payload = $this->read();
            $registry->restoreCompiled($payload['definitions']);
        } catch (InvalidArgumentException|JsonException $exception) {
            throw ContentDefinitionCacheException::invalid('the compiled payload is malformed', $exception);
        }
    }

    /** Remove the selected cache without rebuilding sources or changing configuration. */
    public function clear(): void
    {
        $path = $this->path();
        if (is_file($path) && ! unlink($path)) {
            throw ContentDefinitionCacheException::invalid('the cache cannot be removed');
        }
    }

    /**
     * Explicitly compare source hashes for read-only deployment diagnostics.
     *
     * @return array{present: bool, valid: bool, stale: bool, version: ?string, reason: ?string}
     */
    public function inspect(): array
    {
        $result = ['present' => false, 'valid' => false, 'stale' => false, 'version' => null, 'reason' => null];
        try {
            $enabled = $this->configuration->get('nvl-content.compiled_cache.enabled', false);
            $required = $this->configuration->get('nvl-content.compiled_cache.required', false);
            if (! is_bool($enabled) || ! is_bool($required) || ($required && ! $enabled)) {
                throw ContentDefinitionCacheException::invalid('required cache mode must also be enabled and both flags must be booleans');
            }
            $result['present'] = is_file($this->path());
            $payload = $this->read();
            $result['version'] = $payload['version'];
            $this->compilation->validateCompiled($payload['definitions']);
            $result['valid'] = true;
            $result['stale'] = $payload['sources'] !== $this->loader->sourceManifest();
            if ($result['stale']) {
                $result['reason'] = 'Definition sources changed; regenerate the cache and change the deployment version.';
            }
        } catch (Throwable $exception) {
            $result['reason'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Read and verify the exact scalar envelope without source IO.
     *
     * @return array{definitions: list<array<string, mixed>>, sources: array<string, string>, version: ?string}
     */
    private function read(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            throw ContentDefinitionCacheException::invalid('the cache is missing');
        }
        $size = filesize($path);
        if (! is_int($size) || $size > self::MAXIMUM_BYTES) {
            throw ContentDefinitionCacheException::invalid('the cache exceeds 16 MiB');
        }
        $contents = file_get_contents($path, length: self::MAXIMUM_BYTES + 1);
        if (! is_string($contents) || strlen($contents) > self::MAXIMUM_BYTES) {
            throw ContentDefinitionCacheException::invalid('the cache cannot be read within its size limit');
        }
        $payload = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw ContentDefinitionCacheException::invalid('the envelope must be a JSON object');
        }
        $this->guard->json($payload, 'Compiled Content cache', self::MAXIMUM_BYTES, 64);
        $properties = ['schema_version', 'compiler_abi', 'package_reference', 'version', 'signature', 'sources', 'definitions', 'payload_sha256'];
        if (array_diff(array_keys($payload), $properties) !== [] || array_diff($properties, array_keys($payload)) !== []) {
            throw ContentDefinitionCacheException::invalid('the envelope properties do not match schema 1');
        }
        $digest = $payload['payload_sha256'];
        unset($payload['payload_sha256']);
        if (! is_string($digest) || ! hash_equals($this->json->hash($payload), $digest)
            || $payload['schema_version'] !== 1 || $payload['compiler_abi'] !== ContentCompilationSignature::ABI
            || $payload['package_reference'] !== $this->packageReference()
            || $payload['signature'] !== $this->signature->current()
            || ! is_array($payload['sources']) || ! is_array($payload['definitions'])
            || ! array_is_list($payload['definitions'])
            || ($payload['version'] !== null && ! is_string($payload['version']))) {
            throw ContentDefinitionCacheException::invalid('digest, ABI, package reference or compiler inputs differ');
        }
        $configured = $this->version();
        if ($configured !== null && $configured !== $payload['version']) {
            throw ContentDefinitionCacheException::invalid('the deployment version differs');
        }
        foreach ($payload['sources'] as $source => $hash) {
            if (! is_string($source) || preg_match('~^root-[0-9]+/(?!/)[^\\\\]+$~D', $source) !== 1
                || in_array('..', explode('/', $source), true) || str_contains($source, "\0")
                || ! is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                throw ContentDefinitionCacheException::invalid('a source identity or hash is invalid');
            }
        }
        $definitions = [];
        foreach ($payload['definitions'] as $definition) {
            if (! is_array($definition) || array_is_list($definition)) {
                throw ContentDefinitionCacheException::invalid('compiled definitions must be objects');
            }
            $definitions[] = ContentArrays::stringMap($definition, 'compiled Content definition');
        }

        return ['definitions' => $definitions, 'sources' => $payload['sources'], 'version' => $payload['version']];
    }

    /** Resolve a valid explicit version without enabling required-cache mode implicitly. */
    private function version(?string $requested = null): ?string
    {
        $configured = $this->configuration->get('nvl-content.compiled_cache.version');
        if ($configured !== null && (! is_string($configured) || trim($configured) === '')) {
            throw ContentDefinitionCacheException::invalid('the configured deployment version must be nonempty or null');
        }
        if ($requested !== null && (trim($requested) === '' || ($configured !== null && $requested !== $configured))) {
            throw ContentDefinitionCacheException::invalid('the requested deployment version must match configuration');
        }
        $version = $requested ?? $configured;
        if ($this->configuration->get('nvl-content.compiled_cache.required', false) === true && $version === null) {
            throw ContentDefinitionCacheException::invalid('required mode needs an explicit deployment version');
        }

        return $version;
    }

    /** Resolve the cache path inside the host application without traversing symbolic links. */
    private function path(): string
    {
        $path = $this->configuration->get('nvl-content.compiled_cache.path', $this->application->basePath('bootstrap/cache/nvl-content-definitions.json'));
        if (! is_string($path)) {
            throw ContentDefinitionCacheException::invalid('the cache path must be a string');
        }
        $this->assertSafePath($path);

        return $path;
    }

    /** Reject relative paths, traversal, directories and symlink components before filesystem effects. */
    private function assertSafePath(string $path): void
    {
        $root = rtrim($this->application->basePath(), DIRECTORY_SEPARATOR);
        if (! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || str_contains($path, "\0")) {
            throw ContentDefinitionCacheException::invalid('the cache path must remain inside the host application');
        }
        $segments = explode(DIRECTORY_SEPARATOR, substr($path, strlen($root) + 1));
        $current = $root;
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw ContentDefinitionCacheException::invalid('the cache path contains unsafe components');
            }
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw ContentDefinitionCacheException::invalid('the cache path traverses a symbolic link');
            }
        }
        if (is_dir($path)) {
            throw ContentDefinitionCacheException::invalid('the cache path identifies a directory');
        }
    }

    /** Identify the installed package revision without database or remote lookups. */
    private function packageReference(): string
    {
        if (! InstalledVersions::isInstalled('nvl/content')) {
            return '5.x-dev';
        }

        return InstalledVersions::getReference('nvl/content') ?? InstalledVersions::getPrettyVersion('nvl/content') ?? '5.x-dev';
    }
}
