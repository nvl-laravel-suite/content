<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Http;

use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Tests\Fixtures\Consumer\Data\ContentBlockData;
use Nvl\Content\Tests\Fixtures\Consumer\Data\ContentPlacementData;
use stdClass;

/**
 * Preserves JSON object/list fidelity at the optional HTTP boundary.
 */
final class ContentResponseData
{
    /**
     * @param  array{alias:string,name:string,description:string|null,field:ContentFieldDefinition,jsonSchema:array<string, mixed>}  $preset
     * @return array<string, mixed>
     */
    public static function preset(array $preset): array
    {
        $data = $preset;
        $data['field'] = self::schemaField($preset['field']->toArray());
        $data['jsonSchema'] = self::object($preset['jsonSchema']);

        return $data;
    }

    /**
     * @param  array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}  $definition
     * @return array<string, mixed>
     */
    public static function definition(array $definition): array
    {
        $data = $definition;
        $data['defaults'] = self::object($definition['defaults']);
        $data['schema'] = self::schema($definition['schema']->toArray());
        $data['jsonSchema'] = self::object($definition['jsonSchema']);

        return $data;
    }

    /**
     * @param  array{ownerType:string,ownerId:string,group:string,definitions:list<array{key:string,name:string,description:string|null,category:string,version:int,view:string|null,schema:ContentSchema,defaults:array<string, mixed>,allowedScopes:list<string>,allowedRegions:list<string>,isActive:bool,sortOrder:int,jsonSchema:array<string, mixed>}>,presets:list<array{alias:string,name:string,description:string|null,field:ContentFieldDefinition,jsonSchema:array<string, mixed>}>,groups:list<string>,placements:list<ContentPlacement>,placementLimit:int}  $editor
     * @return array<string, mixed>
     */
    public static function editor(array $editor): array
    {
        return ['ownerType' => $editor['ownerType'], 'ownerId' => $editor['ownerId'], 'group' => $editor['group'], 'placementLimit' => $editor['placementLimit'], 'definitions' => array_map(self::definition(...), $editor['definitions']), 'presets' => array_map(self::preset(...), $editor['presets']), 'groups' => $editor['groups'], 'placements' => array_map(static fn (ContentPlacement $placement): array => self::placement(ContentPlacementData::fromModel($placement)), $editor['placements'])];
    }

    /**
     * @return array<string, mixed>
     */
    public static function block(ContentBlockData $block): array
    {
        $data = $block->toArray();
        $data['values'] = self::object($block->values);
        $data['metadata'] = self::object($block->metadata);
        $translations = [];
        foreach ($block->translations as $locale => $values) {
            $translations[$locale] = self::object($values);
        }
        $data['translations'] = self::object($translations);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function placement(ContentPlacementData $placement): array
    {
        $data = $placement->toArray();
        $data['overrides'] = self::object($placement->overrides);
        if ($placement->block === null) {
            unset($data['block']);
        } else {
            $data['block'] = self::block($placement->block);
        }

        return $data;
    }

    /**
     * @param  array{ownerType:string,ownerId:string,group:string,locale:string,blocks:list<array{id:string,placementId:string,definitionKey:string,key:string|null,region:string,sortOrder:int,view:string,locale:string,values:array<string, mixed>,fieldTypes:array<string, string>,children:list<array<string,mixed>>}>,regions:array<string, list<array{id:string,placementId:string,definitionKey:string,key:string|null,region:string,sortOrder:int,view:string,locale:string,values:array<string, mixed>,fieldTypes:array<string, string>,children:list<array<string,mixed>>}>>,version:string}  $composition
     * @return array<string, mixed>
     */
    public static function composition(array $composition): array
    {
        $data = $composition;
        $data['blocks'] = array_map(self::renderedBlock(...), $composition['blocks']);
        $regions = [];
        foreach ($composition['regions'] as $region => $blocks) {
            $regions[$region] = array_map(self::renderedBlock(...), $blocks);
        }
        $data['regions'] = self::object($regions);

        return $data;
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string, mixed>
     */
    private static function renderedBlock(array $block): array
    {
        foreach (['values', 'fieldTypes', 'children'] as $key) {
            if (! is_array($block[$key] ?? null)) {
                throw new \InvalidArgumentException('Rendered Content must contain native maps and children.');
            }
        }
        $data = $block;
        $data['values'] = self::object($block['values']);
        $data['fieldTypes'] = self::object($block['fieldTypes']);
        $data['children'] = array_map(static function (mixed $child): array {
            if (! is_array($child)) {
                throw new \InvalidArgumentException('Rendered children must be maps.');
            }

            return self::renderedBlock(ContentArrays::stringMap($child, 'rendered child'));
        }, $block['children']);

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @return array{fields: list<array<string, mixed>>}
     */
    private static function schema(array $schema): array
    {
        $fields = $schema['fields'] ?? $schema;
        if (! is_array($fields)) {
            return ['fields' => []];
        }
        $normalized = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $normalized[] = self::schemaField(ContentArrays::stringMap($field, 'content response schema field'));
        }

        return ['fields' => $normalized];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function schemaField(array $field): array
    {
        $settings = $field['settings'] ?? [];
        $field['settings'] = self::object(is_array($settings) ? $settings : []);
        if (isset($field['fields']) && is_array($field['fields'])) {
            $children = [];
            foreach ($field['fields'] as $child) {
                if (is_array($child)) {
                    $children[] = self::schemaField(ContentArrays::stringMap($child, 'content response child schema field'));
                }
            }
            $field['fields'] = $children;
        }
        if (isset($field['item']) && is_array($field['item'])) {
            $field['item'] = self::schemaField(ContentArrays::stringMap($field['item'], 'content response item schema field'));
        }

        return $field;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>|stdClass
     */
    private static function object(array $value): array|stdClass
    {
        return $value === [] ? new stdClass : $value;
    }
}
