<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Nvl\Content\Exceptions\ContentDefinitionMigrationRequiredException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Schema\ContentSchema;

/**
 * Prevents schema-aware writes from silently adopting a newer definition.
 *
 * @internal
 */
final class ContentDefinitionVersionGuard
{
    /** Native Content operation.
     * @param  array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}  $definition
     */
    public function assertCurrent(ContentBlock $block, array $definition): void
    {
        if ($block->definition_version === $definition['version']) {
            return;
        }
        throw ContentDefinitionMigrationRequiredException::forBlock(blockId: $block->id, definition: $definition['key'], storedVersion: $block->definition_version, currentVersion: $definition['version']);
    }
}
