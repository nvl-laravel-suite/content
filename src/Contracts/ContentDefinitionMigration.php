<?php

declare(strict_types=1);

namespace Nvl\Content\Contracts;

use Nvl\Content\Enums\ContentStatus;
use Nvl\Content\Enums\ContentVisibility;

/**
 * Deterministically upgrades one definition through one sequential version step.
 *
 * @api
 */
interface ContentDefinitionMigration
{
    /** Native Content operation. */
    public function definitionKey(): string;

    /** Native Content operation. */
    public function fromVersion(): int;

    /** Native Content operation. */
    public function toVersion(): int;

    /** Native Content operation.
     * @param  array{blockId:string,blockKey:string,scope:string,scopeKey:string,status:ContentStatus,visibility:ContentVisibility,fromVersion:int,toVersion:int,values:array<string, mixed>,translations:array<string, array<string, mixed>>,metadata:array<string, mixed>}  $context
     * @return array{values:array<string, mixed>,translations:array<string, array<string, mixed>>,metadata:array<string, mixed>}
     */
    public function migrate(array $context): array;
}
