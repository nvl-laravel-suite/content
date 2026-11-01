# Content usage recipes

Read [installation](installation.md) for setup and [API](api.md) for full declarations.
`App\...` classes represent application-owned adapters.

## Bind policy, register owner groups and preserve cleanup

Bind `Nvl\Content\Contracts\ContentAuthorization` or set
`nvl-content.authorization.class`. The default adapter invokes
`nvl-content.authorization.callback($ability, $actor, $block, $owner, $context)`
and admits only literal true. No callback means denial. The contract method is
`authorize(ContentAbility $ability, Authenticatable|SystemActor|PublicActor $actor,
?ContentBlock $block = null, ?Model $owner = null, array $context = []): void`.
Use actual host policy for each capability; explicit background/public identities
need grants just as human actors do.

Config `nvl-content.owners` maps aliases to models implementing `ContentOwner`.
That interface supplies `contentGroups()` and `contentPlacements()`;
`Nvl\Content\Traits\HasContent` supplies relation/cleanup integration and accepts
a declared `CONTENT_GROUPS` list. Plain Eloquent models may instead
be composed through the registrar with explicit groups:

```php
use Nvl\Content\Contracts\ContentOwnerRegistrar;

app(ContentOwnerRegistrar::class)->register(
    'article', \App\Models\Article::class, ['content'],
);
```

Register once during host boot, preserving the shared owner/morph alias. Do not
also register the same model through config. Plain-model composition must retain
`ContentOwnerDeletion` integration for atomic hard-deletion cleanup; bypassing the
owner deletion bridge can orphan placements. For plain owners, wrap the existing
delete operation with `ContentOwnerDeletion::execute($owner,$deleteClosure)`; the
closure invokes native deletion once without re-entering the wrapper. Owners with
placements must share the Content connection for atomic deletion.
Native services canonicalize owners
through model scopes and enforce registered groups, including unowned hosts.

## Deployment-owned definitions

Put definitions in `nvl-content.definitions` or `*.content.php` / `*.content.json`
under configured `definition_paths`. Roots, required paths and file/size limits
are deployment configuration. Source files are authoritative; the database mirrors
them for lookups and synchronization.

```php
config(['nvl-content.definitions.hero' => [
    'name' => 'Hero', 'category' => 'marketing', 'version' => 1,
    'allowed_scopes' => ['global'], 'allowed_regions' => ['main'],
    'schema' => ['fields' => [
        ['key' => 'title', 'type' => 'text', 'label' => 'Title',
            'localized' => true, 'required' => true],
        ['key' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled',
            'default' => true],
    ]],
]]);
```

Set definitions before their boot-time compilation, not after resolving services.
The global scope is part of the native defaults; custom scopes declare `key_pattern`.
Use the built-in field shapes below and [API](api.md) for custom adapter contracts.
Register custom `ContentFieldTypeAdapter` classes through
`field_types`, `ContentFieldPreset` through `presets`, and `ContentReferenceResolver`
classes through `references`. Owned references require the ownership-safe contract.

### Built-in field shapes

Each field has `key`, `type`, optional `label` (defaults to the key), `required`,
`localized`, `default`, `fields`, `item`, `settings`, `preset`. Settings are closed
per type; `nullable` is not a field property or built-in setting.

| Type alias | Input value and structure | Allowed settings |
| --- | --- | --- |
| `text`, `textarea`, `email`, `color`, `date`, `date_time` | String; color is hexadecimal, date is `Y-m-d`, date-time is ISO 8601 with offset | `min_length`, `max_length`, `pattern` |
| `url`, `uri` | URL string; URI also permits configured relative links, mail and telephone schemes | String settings above, `allowed_schemes` |
| `select` | One option value; options are a nonempty string list or value-to-label map | String settings above, required `options` |
| `multi_select` | List of option strings | Required `options`, `max_items` |
| `boolean` | Native boolean | None |
| `integer`, `number` | Native integer or finite number respectively | `min`, `max` |
| `rich_text` | HTML string, sanitized on write/render; render projects `['html'=>string]` | None |
| `json` | JSON-compatible PHP value validated against Draft 2020-12 | Required `schema` map |
| `object` | Object map; declares nonempty child `fields` | None |
| `list` | List of values; declares one `item` field definition | `min_items`, `max_items` |
| `repeater`, `table` | List of object rows; declares nonempty child `fields` | `min_items`, `max_items` |
| `media` | One native Media UUID string | `mime_types` |
| `media_collection` | List of native Media UUID strings | `mime_types`, `max_items` |
| `reference` | One opaque ID string resolved through a registered reference alias | Required `reference_type` |
| `reference_list` | List of opaque ID strings | Required `reference_type`, `max_items` |

For example, a reference field is `['key'=>'article','type'=>'reference',
'settings'=>['reference_type'=>'article']]`; the matching resolver registration is
`nvl-content.references.article`. Media fields additionally require the enabled Media
integration. Collection bounds cannot exceed package limits. Built-in adapters accept
explicit null; required fields must be nonempty when publishing. Localized values
belong in `translations`, with nonlocalized structure in `values`. Retain returned
repeater row `_key` identities for localized overrides; table overrides match row indexes.

`ContentDefinitions::sync($actor, $dryRun)` returns create/update/unchanged/orphan
lists and `applied`; it does not edit source definitions. Inspect the plan before
applying catalog changes. Bump definition versions when source semantics change;
register deterministic sequential `ContentDefinitionMigration` classes under
`definition_migrations`. `planMigration($actor, $definition, $limit)` returns a
bounded ready/blocked plan with revisions; `migrate($plan, $actor)` rechecks its
source/revision guards atomically. Never pretend a source-hash change migrated
persisted block values. Compiled-cache flags are separate deployment choices:
`compiled_cache.enabled`, `.required`, `.path`, `.version`.

## Blocks, nulls and publication

After synchronizing the definition catalog under an authorized actor:

```php
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDefinitions;

app(ContentDefinitions::class)->sync($catalogManager);
$blocks = app(ContentBlocks::class);
$block = $blocks->create([
    'definition' => 'hero', 'key' => 'welcome-hero',
    'scope' => 'global', 'scopeKey' => '*',
    'values' => ['enabled' => true],
    'translations' => ['en' => ['title' => 'Welcome']],
], $editor);
$block = $blocks->update($block, [
    'expected_revision' => $block->revision,
    'values' => ['enabled' => false],
], $editor);
$block = $blocks->publish($block, $block->revision, $editor);
```

Create keys are `definition`, `key`, `scope`, `scopeKey`, `visibility`, `values`,
`translations`, `metadata`. Create makes a draft; publish is a separate capability.
Update keys are `expected_revision`, `mode`, `visibility`, `values`, `translations`,
`metadata`. `ContentMutationMode::Patch` is default: nested object maps merge,
lists replace, omitted content stays. Explicit null is retained and validated
against its field; it is not silently removed as a generic merge-patch deletion.
Replace uses the supplied value/metadata/locale sets, including empty defaults.
A null update visibility retains current visibility. Changing a published private
block to public also needs Publish authority.

Publication validates required locales and fields, reference/media eligibility
and current definition version. Archive/restore/delete use separate revision
arguments. Revisions and domain events are produced by the native services;
direct model writes cannot supply equivalent invariants.

## Placements, rendering and snapshots

```php
use Nvl\Content\Services\ContentPlacements;
use Nvl\Content\Services\ContentRenderer;

$placement = app(ContentPlacements::class)->place($block, $article, 'content', [
    'key' => 'hero', 'region' => 'main', 'sortOrder' => 0,
], $editor);
$composition = app(ContentRenderer::class)->render($article, [
    'actor' => $viewer, 'group' => 'content', 'locale' => 'en', 'publicOnly' => true,
]);
```

Place accepts `key`, `region`, `parentId`, `sortOrder`, `isVisible`, `overrides`.
Update accepts its closed revisioned schema. `move($owner, $group, ['placements'=>...],
$actor)` proposes every placement exactly once, each with `id`, `expected_revision`,
`region`, `sortOrder` and optional nullable `parentId`. Tree locks retain group,
owner, region, cycle and depth invariants. `replace` swaps the block at one
revisioned placement; delete removes a placement through native locking.

Live rendering requires explicit `actor` in context; defaults are group `default`,
current Content locale, `publicOnly=true`. Prefer a deliberate declared group and
locale in the host. Anonymous rendering cannot request private content. Rendering
checks Render on the owner/group and View once per distinct block, after canonical
owner admission. Returns ownerType/ownerId/group/locale, nested `blocks`, `regions`
and a version; the host chooses delivery and view assembly.

`ContentReader` supplies block get/find/paginate, placements/groups/summaries,
composition and `resolveScopes`. Scope resolution is bounded and returns localized
values plus source information. Pagination filters are exactly `key`, `scope`,
`scope_key`, `status`, `visibility`, `definition_id`; invalid/unknown filters fail.

Capture with `ContentReader::exportSnapshot($owner, $group, $actor)`. The bounded
snapshot carries immutable block/placement revisions, schemas, localized values,
owner/group identity and checksum/version. Versioned consumers retain integrity
and private-block policy rather than reading mutable live state to reinterpret it.
`exportForCopy` / `importSnapshot` use `ContentCatalogCopyRegistry` registrations and `ContentCatalogCopyAccess` recipient/source policy,
exact source revision/hash, grants, explicit Media maps and canonical target policy.
They are not arbitrary snapshot-array import shortcuts. Do not call internal
snapshot/patch/child writers as if they were complete authorized public operations.

## Optional Media, model extension and verification

Media is disabled by default: `nvl-content.media.enabled=false`. Enable explicitly
for media fields and load the native provider. Preserve `require_public_for_public_blocks`,
`allow_private_for_private_blocks`, maximum-per-field limits and Media authorization.
Private projection URL issuance uses the explicit actor and caps TTL at Media's
per-disk maximum; the host supplies delivery endpoints. Text-only content boots
without Media. Ownership is independently optional, requiring declared resources
and reviewed adoption for owned storage.

Set `nvl-content.models.block` before boot; retain native UUIDs, casts, ownership,
relations, hooks and configured table/connection. Host migrations add custom columns.
Definitions, placements, translations and revisions remain fixed package models.

Services own transactions and place domain events after the actual outer commit.
Map schema, stale revision, definition-version, missing resource and authorization
failures without leaking private values, references or storage/SQL details.
Test patch versus replace/nulls, catalog version migration, View gates on values,
anonymous rendering, whole-tree proposals, snapshot tampering, optional Media
absence and owner rollback. Keep consumer HTTP/editor projection tests separate.
