---
name: nvl-content
description: Use native NVL Content definitions, blocks, placements, rendering and snapshots.
---

Use `ContentDefinitions`, `ContentBlocks`, `ContentPlacements`, `ContentReader` and `ContentRenderer`. Pass an explicit Laravel principal or `SystemActor`, native validated input arrays and expected revisions. Consume models, collections and bounded arrays. Keep HTTP, editor bootstrap, DTO projection and views in the host application.

Preserve schema/locale validation, canonical owner admission, optimistic revisions, tree locks, snapshot checksums and outer-transaction event delivery. Never mutate tables directly to bypass these workflows. Register plain Eloquent owners with explicit group declarations and preserve atomic cleanup through `ContentOwnerDeletion`.

Native workflows refresh Eloquent actors through Core's canonical resolver. External principals retain provider-owned freshness and require a nonnull identity. Owners are reloaded even without Ownership; summaries reload once per owner class. Placement projections with block values, live rendering and snapshot capture additionally authorize `ContentAbility::View` once per distinct block and owner group. Anonymous live rendering requires `publicOnly=true`; anonymous snapshot rendering rejects private blocks without querying live block state. Mutation array concurrency controls use `expected_revision`.

Block pagination accepts only `key`, `scope`, `scope_key`, `status`, `visibility` and `definition_id`. Invalid values and unknown keys fail validation instead of being ignored.

Media is an optional enabled adapter with native authorization. Text-only content requires no Media runtime. Native Ownership declaration and reviewed adoption replace the retired Tenancy bridge. Do not restore Actions, package DTOs, route registration, TypeScript transformation or compatibility facades.

Private Media projections pass the explicit actor to signed URL issuance and cap the requested Content lifetime at Media's per-disk maximum. The host owns the named delivery endpoints.
