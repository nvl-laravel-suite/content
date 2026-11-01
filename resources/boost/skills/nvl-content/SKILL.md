---
name: nvl-content
description: Use when integrating nvl/content code-owned block definitions, typed localized values, reusable blocks, owner placements, rendering, snapshots, definition migrations or optional Media/Ownership.
---

# NVL Content

Use PHP 8.4 / Laravel 13 native services and shaped arrays. The host owns HTTP,
editor payloads, application Actions/DTOs, views and frontend type generation.

## Read the relevant reference

- [Installation](references/installation.md): dependencies, migrations, configuration and publication.
- [Native API](references/api.md): exact declarations, schema/domain values and contracts.
- [Usage](references/usage.md): code definitions, owner registration, block/placement recipes and integrations.

## Choose by domain operation

| Need | Capability |
| --- | --- |
| Synchronize code definitions, inspect presets, plan/apply version migration | `ContentDefinitions` |
| Create/update/publish/archive/restore/delete blocks | `ContentBlocks` |
| Place, update, move, replace or remove owner placements | `ContentPlacements` |
| Blocks, compositions, groups, placements, owner summaries, scoped values, snapshots | `ContentReader` |
| Authorized live composition projection | `ContentRenderer` |
| Owner groups and cleanup integration | `ContentOwnerRegistrar`, `ContentOwnerDeletion` |

Code definitions are authoritative; persisted definition rows are a synchronized
mirror. Synchronize before creating or editing blocks against changed source.
Do not use a database-only editor to replace the deployment-owned catalog.

## Authority, owners and locales

Bind `ContentAuthorization` or configure the host callback; the default denies.
Pass an explicit actor. Native services refresh Eloquent actors and canonical
owners through retained host scopes even without Ownership. External principals
retain their provider-owned freshness contract and need a nonnull identity.
Public/background enums are identities, not automatic policy grants.

Declare owner aliases and exact composition groups. Plain Eloquent owners may
use explicit composition registration; implementing `ContentOwner` is optional
when the registrar receives groups. Retain atomic cleanup on owner hard deletion.

Rendering, snapshot capture and value-bearing placement projections authorize
View per distinct block and owner group in addition to the operation ability.
Anonymous live rendering requires `publicOnly=true`; immutable snapshot consumers
must reject private blocks without consulting mutable live block state.

## Native mutation semantics

- Block creation uses definition key, block key, optional scope/scopeKey and native value maps.
- Block update requires `expected_revision`; Patch merges maps and replaces lists.
- Explicit null is a field value only when its schema permits it; omission is distinct.
- Replace supplies replacement values, metadata and locale set; do not infer patch semantics.
- Placement update uses its own revision; tree move proposes every owner-group placement exactly once.
- Pagination accepts only `key`, `scope`, `scope_key`, `status`, `visibility`, `definition_id`.
- Use definition-schema validation, reference authorization and bounded payload rules directly.

## Models and optional integrations

Select `nvl-content.models.block` before provider boot with a concrete ContentBlock
subclass. Retain UUIDs, required casts, native persistence/query hooks, ownership,
relations and package-configured storage. Fixed definitions, translations,
placements and revisions have no replacement selector. Added fields need host migrations.

Text-only Content needs no Media runtime. Enable `nvl-content.media.enabled`
explicitly for Media fields, retaining native asset/owner authorization and slots.
Private URL issuance uses the explicit actor and Media's per-disk lifetime bound.
The host owns delivery routes. Native Ownership requires reviewed model adoption.

## Atomicity and verification

Keep source-connection transactions, revision records, placement tree locks,
definition-version guards, checksums and outer-commit domain events intact.
Snapshots are immutable domain facts; they are not a generic input DTO or permission.
Test schema/locale/null modes, canonical policy facts, batch bounds, concurrent
placements, immutable snapshots, Media absence and host rollback. Do not expose
internal storage/patch helpers as complete public operations; use [usage](references/usage.md).
