<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Validation\ContentSchemaValidator;
use Nvl\Content\Validation\ContentValueValidator;

/**
 * Compiles source definitions without mutating the running host registry.
 *
 * @internal
 */
final readonly class ContentDefinitionCompilation
{
    /** Retain the registered compiler collaborators for an isolated compilation. */
    public function __construct(private ContentDefinitionLoader $loader, private ContentSchemaValidator $validator, private ContentScopeRegistry $scopes, private ContentSchemaCompiler $compiler, private ContentJsonSchemaBuilder $jsonSchemas, private ContentValueValidator $values) {}

    /**
     * Compile every declared source into a fresh immutable definition set.
     *
     * @return list<array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}>
     */
    public function compile(): array
    {
        $registry = new ContentDefinitionRegistry($this->validator, $this->scopes, $this->compiler, $this->jsonSchemas, $this->values);
        foreach ($this->loader->load() as $definition) {
            $registry->register($definition);
        }

        return $registry->all();
    }

    /**
     * Validate compiled definitions without touching the running registry or sources.
     *
     * @param  list<array<string, mixed>>  $definitions
     */
    public function validateCompiled(array $definitions): void
    {
        $registry = new ContentDefinitionRegistry($this->validator, $this->scopes, $this->compiler, $this->jsonSchemas, $this->values);
        $registry->restoreCompiled($definitions);
    }
}
