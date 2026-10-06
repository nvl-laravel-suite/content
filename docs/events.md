# NVL content events

This document describes the implemented source behavior. Executable acceptance proof is pending the final testing phase. The authoritative machine-readable schema is [event-catalog.json](../resources/event-catalog.json), catalog version `1`. Event `schemaVersion` is independent of catalog version.

## Publication and listener timing

The native host dispatcher receives the captured event after the supplied source connection outer commit, or immediately when that connection has no active transaction.

Callbacks attach to the matching native connection and current nesting record; native outer/savepoint rollback discards the corresponding callbacks.

Missing source transaction records fail before commit; Mail Notifications reports and drops unusable observations.

Host after-commit listeners and queue after_commit policies can add their own deferral after publication. Host transaction infrastructure and dispatcher bindings are preserved.

Local callbacks are not an outbox. Process exit between commit and callback can lose delivery; no crash durability or exactly-once delivery is promised.

One canonical object is dispatched per qualifying producer call. This is local publication, not cross-process deduplication or a guarantee that repeated observations are unique.

Use Nvl\Support\Events\DomainEventDispatcher::dispatch($event, $writerConnection). Native Event::dispatch() is immediate and has no package interception.

## Payload security and no-op behavior

Actor, block/placement identifiers, revisions, enum facts and optional owner/group references. No block content, definition schema or model. Persisted polymorphic owner identity is used.

Existing mutation/revision guards decide publication. Caller-owned outer transactions remain the commit boundary. No extra transaction or global deduplication is invented.

Actor/owner identifiers do not grant access. Listeners must preserve the captured ownership and apply their own authorization when reading storage. Readonly payload fields and native value objects are schema facts; public constructors with mixed arrays do not create a new recursive sanitization boundary. Package producer shapes are documented below; hosts must not attach models, mutable service objects or private arbitrary data.

## Canonical events

| Event | Schema version | Trigger |
| --- | --- | --- |
| [ContentBlockChanged](#contentblockchanged) | 1 | Content block revision/lifecycle changed. |
| [ContentPlacementChanged](#contentplacementchanged) | 1 | Owner placement revision/lifecycle changed. |

### ContentBlockChanged

`Nvl\Content\Events\ContentBlockChanged` · [source](../src/Events/ContentBlockChanged.php) · event schema `1`.

Content block revision/lifecycle changed.

Constructor parameters, in native order:

| Parameter | Native PHP type | Visibility | Default | Collection shape |
| --- | --- | --- | --- | --- |
| `$blockId` | `string` | public | `required` | — |
| `$event` | `Nvl\Content\Enums\ContentRevisionEvent` | public | `required` | — |
| `$revision` | `int` | public | `required` | — |
| `$actor` | `Nvl\Content\Data\ContentActorData` | public | `required` | — |
| `$schemaVersion` | `int` | public | `1` | — |

Public payload fields:

| Field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$blockId` | `string` | — |
| `$event` | `Nvl\Content\Enums\ContentRevisionEvent` | — |
| `$revision` | `int` | — |
| `$actor` | `Nvl\Content\Data\ContentActorData` | — |
| `$schemaVersion` | `int` | — |

Producers and exact scheduling connection expressions (variables are resolved in the linked source):

| Producer | Source connection |
| --- | --- |
| [Actions/ArchiveContentBlockAction.php](../src/Actions/ArchiveContentBlockAction.php) | `$model->getConnection()` |
| [Actions/CreateContentBlockAction.php](../src/Actions/CreateContentBlockAction.php) | `$block->getConnection()` |
| [Actions/DeleteContentBlockAction.php](../src/Actions/DeleteContentBlockAction.php) | `$model->getConnection()` |
| [Actions/PublishContentBlockAction.php](../src/Actions/PublishContentBlockAction.php) | `$model->getConnection()` |
| [Actions/RestoreContentBlockAction.php](../src/Actions/RestoreContentBlockAction.php) | `$model->getConnection()` |
| [Actions/UpdateContentBlockAction.php](../src/Actions/UpdateContentBlockAction.php) | `$model->getConnection()` |
| [Services/ContentBlockDefinitionMigrator.php](../src/Services/ContentBlockDefinitionMigrator.php) | `$block->getConnection()` |

### ContentPlacementChanged

`Nvl\Content\Events\ContentPlacementChanged` · [source](../src/Events/ContentPlacementChanged.php) · event schema `1`.

Owner placement revision/lifecycle changed.

Constructor parameters, in native order:

| Parameter | Native PHP type | Visibility | Default | Collection shape |
| --- | --- | --- | --- | --- |
| `$placementId` | `string` | public | `required` | — |
| `$event` | `Nvl\Content\Enums\ContentPlacementEvent` | public | `required` | — |
| `$revision` | `int` | public | `required` | — |
| `$actor` | `Nvl\Content\Data\ContentActorData` | public | `required` | — |
| `$ownerType` | `?string` | public | `null` | — |
| `$ownerId` | `?string` | public | `null` | — |
| `$group` | `?string` | public | `null` | — |
| `$blockId` | `?string` | public | `null` | — |
| `$schemaVersion` | `int` | public | `1` | — |

Public payload fields:

| Field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$placementId` | `string` | — |
| `$event` | `Nvl\Content\Enums\ContentPlacementEvent` | — |
| `$revision` | `int` | — |
| `$actor` | `Nvl\Content\Data\ContentActorData` | — |
| `$ownerType` | `?string` | — |
| `$ownerId` | `?string` | — |
| `$group` | `?string` | — |
| `$blockId` | `?string` | — |
| `$schemaVersion` | `int` | — |

Producers and exact scheduling connection expressions (variables are resolved in the linked source):

| Producer | Source connection |
| --- | --- |
| [Actions/DeleteContentPlacementAction.php](../src/Actions/DeleteContentPlacementAction.php) | `$model->getConnection()` |
| [Actions/PlaceContentBlockAction.php](../src/Actions/PlaceContentBlockAction.php) | `$placement->getConnection()` |
| [Actions/ReorderContentPlacementsAction.php](../src/Actions/ReorderContentPlacementsAction.php) | `$placement->getConnection()` |
| [Actions/ReplaceContentPlacementAction.php](../src/Actions/ReplaceContentPlacementAction.php) | `$model->getConnection()` |
| [Actions/UpdateContentPlacementAction.php](../src/Actions/UpdateContentPlacementAction.php) | `$model->getConnection()` |
| [Services/ContentBlockPlacementSynchronizer.php](../src/Services/ContentBlockPlacementSynchronizer.php) | `$placement->getConnection()` |

## Referenced payload types

Native event field types are listed above; nested declared fields and backed enum values follow. Private captured envelopes are included because serialized/queued objects retain them. Dates use `Carbon\CarbonImmutable`. Spatie Data serialization can also carry its protected transformation metadata; recursive graph acceptance checks remain pending.

### ContentActorData

`Nvl\Content\Data\ContentActorData` · [source](../src/Data/ContentActorData.php).

| Declared public field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$type` | `?string` | — |
| `$id` | `int\|string\|null` | — |
| `$system` | `bool` | — |

### ContentPlacementEvent

`Nvl\Content\Enums\ContentPlacementEvent` · [source](../src/Enums/ContentPlacementEvent.php).

Backed string values: `Created = created`, `Updated = updated`, `Deleted = deleted`.

### ContentRevisionEvent

`Nvl\Content\Enums\ContentRevisionEvent` · [source](../src/Enums/ContentRevisionEvent.php).

Backed string values: `Created = created`, `Updated = updated`, `Published = published`, `Archived = archived`, `Deleted = deleted`, `Restored = restored`, `Migrated = migrated`.

## Deferred acceptance checks

Final testing must compare catalog types/defaults/aliases with actual classes, recursively inspect producer payloads, and prove source outer commit, nested rollback, unrelated connection independence and retry behavior without an uncommitted test-harness transaction. Where applicable it must cover legacy exact/cached/queued listeners, canonical fakes and wildcard delivery, tenant capture, package no-op guards and observational failure containment. This document does not report those checks as passing.
