# Upgrading to NVL Light Content

Native workflows now refresh canonical Eloquent actor facts before policy and validation; external identities require a nonnull identifier. Register owners and pass persisted canonical identities. Ordinary unscoped owners receive the same fresh-state checks as isolated owners, and summaries reload owners in batches.

Grant `ContentAbility::View` for the blocks returned through placement projections, live rendering and snapshot capture, in addition to the relevant composition/list permission. Anonymous live renders cannot request private values; anonymous frozen renders reject private snapshots. Authorized immutable snapshots retain their live-block independence. Private Media projections now pass the explicit actor to native signed URL issuance and cap their lifetime at Media's configured maximum. No schema migration is required.

Block pagination now rejects unknown or malformed filters. Use only `key`, `scope`, `scope_key`, `status`, `visibility` and `definition_id`; pass mutation concurrency controls as `expected_revision` where the native array contract requires them.

Replace Actions and the aggregate facade with `ContentDefinitions`, `ContentBlocks`, `ContentPlacements`, `ContentReader` and `ContentRenderer`. Supply an explicit principal or `SystemActor`; pass native arrays and consume models, collections, paginators and domain arrays. There are no compatibility aliases.

Rendered compositions and snapshots are native arrays. Snapshot records preserve camelCase fields, serialized schema arrays, enum string visibility and canonical checksums. Keep the complete ownership envelope when storing or transporting snapshots. Catalog copies still require explicit native Ownership grants.

Move HTTP routes/controllers, editor bootstrap, API DTOs and Blade views into your application. Optional Spatie Data projection belongs to that consumer. The package no longer registers routes, TypeScript metadata or bundled views. View validation remains available for host-supplied views.

Enable Media explicitly and supply its authorization policy when using Media fields. Register plain owners with explicit groups and wrap hard deletion with the existing Content cleanup service. Existing `ContentOwner` consumers remain supported. Replace retired Tenancy declarations through reviewed native Ownership adoption; no consumer data is deleted automatically.
