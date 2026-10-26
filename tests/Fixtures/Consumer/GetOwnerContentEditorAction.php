<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentOwnerRegistry;
use Nvl\Content\Services\ContentReader;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Support\Enums\SystemActor;

/**
 * Builds the complete bounded projection for one authorized Content editor.
 *
 * Delegation to the catalog and placement Actions is deliberate orchestration
 * so their authorization, ordering, and row-bound contracts remain canonical.
 *
 * @api
 */
final readonly class GetOwnerContentEditorAction
{
    public function __construct(private ContentDefinitions $listDefinitions, private ContentDefinitions $listPresets, private ContentReader $listGroups, private ContentReader $listPlacements, private ContentOwnerRegistry $owners) {}

    /**
     * Return the complete typed bootstrap payload for one consumer-owned editor.
     */
    /** @return array{ownerType:string,ownerId:string,group:string,definitions:list<array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}>,presets:list<array{alias:string,name:string,description:string|null,field:ContentFieldDefinition,jsonSchema:array<string, mixed>}>,groups:list<string>,placements:list<ContentPlacement>,placementLimit:int} */
    public function execute(Model $owner, string $group, Authenticatable|SystemActor $actor): array
    {
        $definitions = array_values($this->listDefinitions->all($actor)->all());
        $presets = array_values($this->listPresets->presets($actor)->all());
        $groups = array_values($this->listGroups->groups($owner, $actor)->all());
        $placements = $this->listPlacements->placements($owner, $group, $actor, includeBlocks: true);

        return ['ownerType' => $this->owners->type($owner), 'ownerId' => $this->owners->id($owner), 'group' => $group, 'definitions' => $definitions, 'presets' => $presets, 'groups' => $groups, 'placements' => array_values($placements->all()), 'placementLimit' => ContentConfiguration::positiveInteger('nvl-content.placements.maximum_per_group', 1000)];
    }
}
