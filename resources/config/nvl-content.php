<?php

declare(strict_types=1);
use Nvl\Content\Definitions\Tables\ContentTables;
use Nvl\Content\Services\ConfiguredContentAuthorization;

return [
    'connection' => null,
    'tables' => ['definitions' => ContentTables::Definitions, 'blocks' => ContentTables::Blocks, 'blocks_i18n' => ContentTables::BlocksI18n, 'placements' => ContentTables::Placements, 'revisions' => ContentTables::Revisions],
    'migrations' => ['enabled' => true],
    'authorization' => ['class' => ConfiguredContentAuthorization::class, 'callback' => null],
    /*
    |--------------------------------------------------------------------------
    | Source-controlled block definitions
    |--------------------------------------------------------------------------
    |
    | Inline definitions and *.content.php / *.content.json files share the
    | same shape. Directories are scanned deterministically and files remain
    | authoritative; database rows are a queryable synchronization mirror.
    |
    */
    'definitions' => [],
    'definition_paths' => [resource_path('content')],
    'required_definition_paths' => [],
    'allowed_definition_roots' => [base_path()],
    'compiled_cache' => ['enabled' => false, 'path' => base_path('bootstrap/cache/nvl-content-definitions.json'), 'required' => false, 'version' => env('NVL_CONTENT_DEFINITIONS_VERSION')],
    'scopes' => ['global' => ['key_pattern' => '/^(?:\*|[A-Za-z0-9][A-Za-z0-9_.:-]{0,190})$/']],
    'owners' => [],
    'references' => [],
    'field_types' => [],
    'presets' => [],
    'locales' => ['available' => [], 'required_on_publish' => []],
    'routes' => ['management' => ['enabled' => false, 'prefix' => 'nvl/api/v1/content', 'name' => 'nvl.content.management.', 'middleware' => ['api', 'auth']], 'public' => ['enabled' => false, 'prefix' => 'nvl/api/v1/content', 'name' => 'nvl.content.public.', 'middleware' => ['api', 'throttle:120,1']]],
];
