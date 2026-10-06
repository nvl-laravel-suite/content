<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use InvalidArgumentException;
use Nvl\Content\Data\ContentActorData;
use Nvl\Content\Data\ContentDefinitionData;
use Nvl\Content\Data\ContentSchemaData;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Schema\ContentDefinitionSource;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Validation\ContentSchemaValidator;
use Nvl\Content\Validation\ContentValueValidator;

/**
 * In-memory source of truth for available block definitions.
 */
final class ContentDefinitionRegistry
{
    /** @var array<string, ContentDefinitionData> */
    private array $definitions = [];

    public function __construct(
        private readonly ContentSchemaValidator $validator,
        private readonly ContentScopeRegistry $scopes,
        private readonly ContentSchemaCompiler $compiler,
        private readonly ContentJsonSchemaBuilder $jsonSchemas,
        private readonly ContentValueValidator $values,
    ) {}

    public function register(ContentDefinitionSource $source): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,190}$/', $source->key) !== 1) {
            throw new InvalidArgumentException(
                "Content definition key [{$source->key}] is invalid.",
            );
        }

        if (isset($this->definitions[$source->key])) {
            throw new InvalidArgumentException(
                "Content definition [{$source->key}] is already registered.",
            );
        }

        if ($source->version < 1 || $source->sortOrder < 0) {
            throw new InvalidArgumentException(
                "Content definition [{$source->key}] has invalid version or order values.",
            );
        }

        $schema = $this->compiler->compile($source->schema);
        $definition = new ContentDefinitionData(
            key: $source->key,
            name: $source->name,
            description: $source->description,
            category: $source->category,
            version: $source->version,
            view: $source->view,
            schema: ContentSchemaData::fromSchema($schema),
            defaults: $source->defaults,
            allowedScopes: $source->allowedScopes,
            allowedRegions: $source->allowedRegions,
            isActive: $source->isActive,
            sortOrder: $source->sortOrder,
            jsonSchema: $this->jsonSchemas->definition(
                $source->key,
                $source->version,
                $schema,
            ),
        );
        $this->assertMetadata($definition);
        $this->validator->validate($schema);
        $this->values->assertDefaults($schema);
        $this->values->validate(
            schema: $schema,
            values: $definition->defaults,
            translations: [],
            actor: ContentActorData::system(),
            visibility: ContentVisibility::Private,
            resolveExternal: false,
        );
        ContentArrays::stringMap(
            $definition->defaults,
            "content definition {$definition->key} defaults",
        );
        $this->assertAliases($definition->allowedScopes, 'scope', $definition->key);
        $this->assertAliases($definition->allowedRegions, 'region', $definition->key);
        $this->scopes->assertRegistered($definition->allowedScopes);
        $this->definitions[$definition->key] = $definition;
        ksort($this->definitions);
    }

    public function get(string $key): ContentDefinitionData
    {
        return $this->definitions[$key]
            ?? throw new InvalidArgumentException(
                "Content definition [{$key}] is not registered.",
            );
    }

    /**
     * @return list<ContentDefinitionData>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * Restore a fully validated compiled set without invoking the schema compiler.
     *
     * @param  array<array-key, mixed>  $definitions  Untrusted decoded cache input, validated before restoration.
     */
    public function restoreCompiled(array $definitions): void
    {
        (new ContentPayloadGuard)->json($definitions, 'Compiled Content definitions', 16_777_216, 64);
        if (! array_is_list($definitions) || count($definitions) > 500) {
            throw new InvalidArgumentException('Compiled Content definitions must be a bounded list.');
        }

        $restored = [];
        foreach ($definitions as $data) {
            if (! is_array($data) || array_is_list($data)) {
                throw new InvalidArgumentException('Compiled Content definitions must be objects.');
            }
            $properties = ['key', 'name', 'description', 'category', 'version', 'view', 'schema', 'defaults', 'allowedScopes', 'allowedRegions', 'isActive', 'sortOrder', 'jsonSchema'];
            if (array_diff(array_keys($data), $properties) !== [] || array_diff($properties, array_keys($data)) !== []) {
                throw new InvalidArgumentException('Compiled Content definition properties are invalid.');
            }
            foreach (['key', 'name', 'category'] as $property) {
                if (! is_string($data[$property])) {
                    throw new InvalidArgumentException('Compiled Content definition text is invalid.');
                }
            }
            foreach (['description', 'view'] as $property) {
                if ($data[$property] !== null && ! is_string($data[$property])) {
                    throw new InvalidArgumentException('Compiled Content optional metadata is invalid.');
                }
            }
            foreach (['schema', 'defaults', 'allowedScopes', 'allowedRegions', 'jsonSchema'] as $property) {
                if (! is_array($data[$property])) {
                    throw new InvalidArgumentException('Compiled Content definition arrays are invalid.');
                }
            }
            if (! is_int($data['version']) || $data['version'] < 1
                || ! is_int($data['sortOrder']) || $data['sortOrder'] < 0
                || ! is_bool($data['isActive'])
                || preg_match('/^[a-z][a-z0-9_.-]{0,190}$/', $data['key']) !== 1) {
                throw new InvalidArgumentException('Compiled Content definition identity or version is invalid.');
            }
            if (isset($restored[$data['key']]) || isset($this->definitions[$data['key']])) {
                throw new InvalidArgumentException('Compiled Content definition identity is duplicated.');
            }

            $schema = ContentSchema::fromArray($data['schema']);
            $definition = new ContentDefinitionData(
                key: $data['key'], name: $data['name'], description: $data['description'],
                category: $data['category'], version: $data['version'], view: $data['view'],
                schema: ContentSchemaData::fromSchema($schema),
                defaults: ContentArrays::stringMap($data['defaults'], 'compiled Content defaults'),
                allowedScopes: $this->compiledAliases($data['allowedScopes']),
                allowedRegions: $this->compiledAliases($data['allowedRegions']),
                isActive: $data['isActive'], sortOrder: $data['sortOrder'],
                jsonSchema: ContentArrays::stringMap($data['jsonSchema'], 'compiled Content JSON schema'),
            );
            $this->assertMetadata($definition);
            $this->assertAliases($definition->allowedScopes, 'scope', $definition->key);
            $this->assertAliases($definition->allowedRegions, 'region', $definition->key);
            $this->scopes->assertRegistered($definition->allowedScopes);
            $this->validator->validate($schema);
            $this->values->assertDefaults($schema);
            $this->values->validate($schema, $definition->defaults, [], ContentActorData::system(), ContentVisibility::Private, resolveExternal: false);
            $restored[$definition->key] = $definition;
        }

        $this->definitions = [...$this->definitions, ...$restored];
        ksort($this->definitions);
    }

    /**
     * Validate the exact string-list shape before constructing a compiled DTO.
     *
     * @param  array<array-key, mixed>  $aliases
     * @return list<string>
     */
    private function compiledAliases(array $aliases): array
    {
        if (! array_is_list($aliases)) {
            throw new InvalidArgumentException('Compiled Content aliases must be lists.');
        }
        foreach ($aliases as $alias) {
            if (! is_string($alias)) {
                throw new InvalidArgumentException('Compiled Content aliases must be strings.');
            }
        }

        return $aliases;
    }

    /**
     * @param  list<string>  $aliases
     */
    private function assertAliases(array $aliases, string $kind, string $definition): void
    {
        if ($aliases === []) {
            throw new InvalidArgumentException(
                "Content definition [{$definition}] requires at least one {$kind}.",
            );
        }

        foreach ($aliases as $alias) {
            if (preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $alias) !== 1) {
                throw new InvalidArgumentException(
                    "Content definition [{$definition}] contains invalid {$kind} [{$alias}].",
                );
            }
        }

        if (count($aliases) !== count(array_unique($aliases))) {
            throw new InvalidArgumentException(
                "Content definition [{$definition}] contains duplicate {$kind} aliases.",
            );
        }
    }

    private function assertMetadata(ContentDefinitionData $definition): void
    {
        if (trim($definition->name) === '' || mb_strlen($definition->name) > 191) {
            throw new InvalidArgumentException(
                "Content definition [{$definition->key}] has an invalid name.",
            );
        }

        if (preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $definition->category) !== 1) {
            throw new InvalidArgumentException(
                "Content definition [{$definition->key}] has an invalid category.",
            );
        }

        if ($definition->description !== null
            && strlen($definition->description) > 65_000) {
            throw new InvalidArgumentException(
                "Content definition [{$definition->key}] description is too large.",
            );
        }

        if ($definition->view !== null
            && (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,254}$/', $definition->view) !== 1
                || str_contains($definition->view, '..')
                || str_contains($definition->view, '//'))) {
            throw new InvalidArgumentException(
                "Content definition [{$definition->key}] has an invalid view name.",
            );
        }
    }
}
