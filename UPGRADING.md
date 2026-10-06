# Upgrading

## Optional tenancy adoption

Deploy the nullable Content ownership expansion before enabling tenancy. Under
maintenance, record reviewed `content.blocks` mappings, run bounded backfill
until its placement phase completes, verify row counts/checksums and canonical
owner equality, convert stored format-1 publication snapshots through
`Content::adoptSnapshot()`, then activate final constraints. Drain old jobs and
restart workers before opening writes. Do not disable the tenancy flag after
activation or treat missing owner/Media/reference registrations as global data.

Definitions are platform code vocabulary and are synchronized only inside an
authorized `TenantRunner::platform()` operation. They are not tenant-owned
catalog rows and must not be copied or edited per tenant.

## 1.0

This is the first stable contract. There are no compatibility aliases for
consumer-owned block implementations. Import application data through
application-owned code that maps old definition keys, scopes, localized JSON,
placements, and Media IDs into the public Content Actions.

Before every upgrade, run `php artisan nvl:content:doctor --strict`, back up the
five Content tables, deploy schema changes before code that consumes them, and
run `php artisan nvl:content:definitions:sync --dry-run` before synchronization.
Definitions are source-authoritative: deleting a definition file or config entry
orphans its mirror and never silently deletes content blocks.

If the application owns pre-existing compatible Content tables, keep
`nvl-content.migrations.enabled` disabled for that schema unless migration history
has been explicitly reconciled. Package migrations fail closed on table-name
collisions and never adopt an existing table implicitly.

The unreleased v1 schema stores actor identifiers as nullable strings, not
numeric morph columns. Because the package is not published, update the
original migrations and rebuild disposable development databases rather than
adding transitional migrations.

Patch is the default update mode. Callers that intend complete replacement must
send `ContentMutationMode::Replace` explicitly. Parent and child placements
must use one owner, group, and region; remove leaf placements before their
parents and remove all placements before deleting a reusable block.

The pre-release owner resolver boundary has been removed. Register aliases
directly to Eloquent model classes that implement `ContentOwner` and use
`HasContent`. Placement DTOs no longer accept `ownerType` or `ownerId`;
Actions, renderers, snapshots, and the facade receive the persisted owner model
plus an explicit group. Rebuild disposable databases so placements include the
group column and group-scoped unique/index contracts.

Pre-release resolver implementations must use the final
`ContentReferenceResolver` signatures: both `exists()` and `display()` receive
`ContentValidationContext`. Read actor, locale, resolved owner, visibility,
field path, and public/preview state from that context. Pages and Templates now
delegate their actual actors into Content, so consumer authorization must
explicitly permit those renders instead of relying on an internal system
bypass.

Placement mutations require an atomic-lock-capable cache store. Configure
`nvl-content.placements.lock_seconds` and `lock_wait_seconds` for the deployment
environment; `nvl:content:doctor --strict` fails when the active cache store
cannot provide locks. Contract changes to a synchronized definition must also
increase its version.

Every stored definition version change now requires an explicit sequential
`ContentDefinitionMigration` chain whenever older blocks exist. Deploy and
register those classes before synchronizing the new source version, run
`nvl:content:definitions:migrate --dry-run`, apply bounded batches, and finish
with the strict doctor. Editing or publishing an old block no longer adopts the
new schema implicitly; it fails with `definition_migration_required` until its
atomic migration succeeds.

The final pre-release rich-content contract uses semantic presets rather than
consumer-copied object schemas. Replace repeated image/link/button/banner
shapes with the built-in preset aliases where appropriate, then increase each
affected definition version. Definitions and the preset catalog now expose
compiled JSON Schema documents; rendered presets return typed DTOs and backed
enums. Nested localized leaves belong in translation rows while their parent
structure remains in base values.

Custom `ContentFieldPreset` implementations must implement `validate()` for
final locale-resolved invariants and `jsonSchema()` for the equivalent editor
contract. Extending `AbstractContentFieldPreset` supplies pass-through
implementations. The built-in image preset now rejects publication of a
non-decorative Media value without resolved alt text.

Registry authoring inputs use the internal `ContentDefinitionSource`; public
registry output remains `ContentDefinitionData` and now contains a typed
`ContentSchemaData`. Preset fields and composition snapshot blocks are typed
DTOs as well. Regenerate declarations with
`php artisan nvl:data:types:generate`, commit the resulting artifacts, and run
`php artisan nvl:data:types:check`.

## Shared owner registry compatibility

Declare owner classes in `nvl-core.owners`, for example `'owners' => [Article::class]`, and reference the same model class from each package capability. Laravel's `getMorphClass()` is the stored owner identity: it returns the host-authored morph alias or the FQCN when no map exists. Core declarations and package allowlists do not add or enforce a host morph map and do not grant authorization.

Legacy alias references remain read compatibility during major 5 and are removed in major 6. A legacy configured alias must agree with the model's current `getMorphClass()`; mismatches are diagnostics and require a host decision. Doctor can inspect declared package owner columns for stored-versus-current identities without rewriting them. If the host introduces or changes its morph map, review and convert only the affected stored columns and reconcile host relationships before cutover. No automatic owner-data conversion or `nvl:owners:upgrade` is provided. Rebuild configuration caches and restart workers after the coordinated change.

## Shared Doctor integration

The loaded package provider now contributes its existing inspection checks to Core's `nvl:doctor --strict --format=json`. The package command remains available. The shared gate fails errors and, in strict mode, warnings; no data upgrade is required for diagnostics.


## Infrastructure option inheritance

Use `locks.store` for shared definition/placement locking, with optional `locks.definitions.store` and `locks.placements.store` overrides. Existing lifetime and wait settings remain under `definition_sync` and `placements`. Route middleware can inherit Core with null, and `authorization.guard` selects a guard for bare `auth` entries. Preserve authorization callbacks/contracts and deliberate explicit middleware lists. Rebuild configuration caches and restart workers.

## Next major: isolated schema identities

This is a breaking schema identity change. Back up storage and migration history, pause writes/workers, install this code with automatic package migrations disabled, and select one owner for migrations (vendor or published).

```sh
php artisan nvl:doctor --strict --format=json
php artisan nvl:schema:upgrade --package=content --claim-legacy --migration-owner=vendor --dry-run --format=json
php artisan nvl:schema:upgrade --package=content --claim-legacy --migration-owner=vendor --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Declare each published path and canonical identity explicitly in `nvl-core.migrations.published`; retimestamped history also needs an exact `legacy` mapping. Use `--migration-owner=vendor` after manually archiving declared copies outside loaded paths, or `--migration-owner=published` after manually replacing executable copies with current migration code and disabling vendor loading. The plan verifies ownership and preserves batches; checksums do not automatically claim files. Modified host copies remain host-owned. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, run `nvl:schema:preflight` with the same selected paths and connection, then migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.

## Major 5 cache and lock identities

Placement locks retain `nvl:content:placement-owner:` outside the tenant identity in every runtime mode. Definition synchronization keeps `nvl:content:definitions:sync`. Existing generic tenant lock entries are never acquired or removed.

Drain old mutation workers and maintenance processes, then wait for their outstanding lock leases to end before starting the new major across all nodes. Running old and new lock prefixes concurrently would create independent serialization domains. Restart workers after cutover; preserve host-selected stores and keys, and do not flush a shared cache to remove old NVL entries.

## Tagged consumer PHP boundary

Use source `@api` workflows, extension contracts, and value types for application integration. Direct use of untagged implementations or `@internal` members is unsupported. This classification keeps existing concrete Action signatures and runtime behavior; it does not authorize package model persistence, ad hoc queries, relation traversal, or generic model serialization. Returned models are identity/result handles with only the explicitly declared in-memory read fields described in the README.

`HasContent::contentPlacements()` are internal storage or lifecycle seams. Migrate direct traversal, eager/lazy loading, and aggregate queries to the package authorized read Actions or explicit C1 host scopes/adapters. Native host queries and opted-in Translatable behavior remain supported.

`ContentBlockData::fromModel` and `ContentPlacementData::fromModel` are internal projectors. Use `GetContentBlockAction` and `ListContentPlacementsAction` with their required actor/group context to obtain their public DTO results.
