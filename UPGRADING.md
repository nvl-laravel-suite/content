# Upgrading to NVL Light Content

Native workflows now refresh canonical Eloquent actor facts before policy and validation; external identities require a nonnull identifier. Register owners and pass persisted canonical identities. Ordinary unscoped owners receive the same fresh-state checks as isolated owners, and summaries reload owners in batches.

Grant `ContentAbility::View` for the blocks returned through placement projections, live rendering and snapshot capture, in addition to the relevant composition/list permission. Anonymous live renders cannot request private values; anonymous frozen renders reject private snapshots. Authorized immutable snapshots retain their live-block independence. Private Media projections now pass the explicit actor to native signed URL issuance and cap their lifetime at Media's configured maximum. No schema migration is required.

Block pagination now rejects unknown or malformed filters. Use only `key`, `scope`, `scope_key`, `status`, `visibility` and `definition_id`; pass mutation concurrency controls as `expected_revision` where the native array contract requires them.

Replace Actions and the aggregate facade with `ContentDefinitions`, `ContentBlocks`, `ContentPlacements`, `ContentReader` and `ContentRenderer`. Supply an explicit principal or `SystemActor`; pass native arrays and consume models, collections, paginators and domain arrays. There are no compatibility aliases.

Rendered compositions and snapshots are native arrays. Snapshot records preserve camelCase fields, serialized schema arrays, enum string visibility and canonical checksums. Keep the complete ownership envelope when storing or transporting snapshots. Catalog copies still require explicit native Ownership grants.

Move HTTP routes/controllers, editor bootstrap, API DTOs and Blade views into your application. Optional Spatie Data projection belongs to that consumer. The package no longer registers routes, TypeScript metadata or bundled views. View validation remains available for host-supplied views.

Enable Media explicitly and supply its authorization policy when using Media fields. Register plain owners with explicit groups and wrap hard deletion with the existing Content cleanup service. Existing `ContentOwner` consumers remain supported. Replace retired Tenancy declarations through reviewed native Ownership adoption; no consumer data is deleted automatically.

`date_time` fields now require RFC 3339 offsets with a colon (`Z` or `+02:00`); values such as `2026-01-01T10:00:00+0000` are rejected. Fractional seconds of any precision are accepted.

`rich_text.maximum_input_length` now counts characters, not bytes, matching the generated JSON Schema `maxLength`. Multibyte rich text that used to be rejected below the limit is accepted; lower the setting if you relied on it as a byte cap. `validation.maximum_payload_bytes` still bounds the total payload in bytes. The limit applies to the sanitized value that is stored. Rich text near the limit that sanitizing lengthens, for example by escaping `&` to `&amp;`, is now rejected; raise the setting if editors hit it.

Publishing now rejects empty items (`null`, `''` or whitespace) in lists whose item definition is `required`. Fix such stored drafts before publishing them.
