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
`content.migrations.enabled` disabled for that schema unless migration history
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
`content.placements.lock_seconds` and `lock_wait_seconds` for the deployment
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

Move model identity declarations to `nvl-core.owners` and reference the alias from `content` capability configuration as described in the [README](README.md#shared-owner-identity). Preserve package contracts, resolvers/handlers, visibility scopes, and mutation authorization. Core does not grant package capabilities. Conflicting aliases or multiple canonical aliases for one model fail before use.

Legacy class inputs remain accepted for one major cycle and are reported through Core diagnostics. Existing Content, Taxonomy, and Metafields mappings keep their established aliases. Legacy SEO, Media, Comments, Templates, Pages, and Translatable class-backed behavior does not automatically create a new morph alias. Existing host morph mappings are respected.

Adding a canonical Core alias changes Laravel's write-time morph type for that model. Before adding it to an existing class-backed deployment, explicitly convert the known package-owned morph columns and reconcile every other affected host relationship. Keep unrelated rows and host-owned morph tables unchanged. This release performs no automatic owner-data conversion and does not ship `nvl:owners:upgrade`. Preserve existing aliases when no conversion is required, rebuild configuration caches, and restart workers after the cutover.

## Shared Doctor integration

The loaded package provider now contributes its existing inspection checks to Core's `nvl:doctor --strict --format=json`. The package command remains available. The shared gate fails errors and, in strict mode, warnings; no data upgrade is required for diagnostics.


## Infrastructure option inheritance

Use `locks.store` for shared definition/placement locking, with optional `locks.definitions.store` and `locks.placements.store` overrides. Existing lifetime and wait settings remain under `definition_sync` and `placements`. Route middleware can inherit Core with null, and `authorization.guard` selects a guard for bare `auth` entries. Preserve authorization callbacks/contracts and deliberate explicit middleware lists. Rebuild configuration caches and restart workers.

## Next major: isolated schema identities

This is a breaking schema identity change. Back up storage and migration history, pause writes/workers, install this code with automatic package migrations disabled, and select one owner for migrations (vendor or published).

```sh
php artisan nvl:doctor --strict --format=json
php artisan nvl:schema:upgrade --package=content --claim-legacy --dry-run --format=json
php artisan nvl:schema:upgrade --package=content --claim-legacy --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Unmodified published files, including changed timestamps, map by verified checksum to the exact vendor migration identity and current package migration implementation. Modified host copies remain host-owned. Disable vendor loading when retaining a published owner; duplicate ownership fails before migration. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.
