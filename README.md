# NVL Content

Native Laravel services for schema-defined, localized reusable blocks, owner placements, revisions and checksummed content snapshots. PHP 8.4+, Laravel 13; `nvl/core` and `nvl/translatable` are runtime foundations. JSON Schema validation and HTML sanitization retain their audited engines.

## Services

- `ContentDefinitions::sync($actor)`, `planMigration($actor)`, `migrate($plan, $actor)` manage definitions and bounded version migrations.
- `ContentBlocks::create($input, $actor)`, `update($block, $input, $actor)`, `publish($block, $expectedRevision, $actor)`, `archive`, `delete` and `restore` return native models or mutation outcomes.
- `ContentPlacements::place($block, $owner, $group, $input, $actor)`, `update`, `move`, `replace` and `delete` retain tree, revision and atomicity checks. `move` returns the ordered placement models.
- `ContentReader` returns models, Laravel collections/paginators, scope values and native compositions. `exportSnapshot($owner, $group, $actor)` produces durable scalar arrays. Explicit native Ownership catalog-copy grants are revalidated by `exportForCopy` and `importSnapshot`.
- `ContentRenderer::render(Model $owner, array $context = []): array` requires an explicit `actor` and accepts `group`, `locale` and `publicOnly` context. It returns bounded domain content. Hosts supply presentation, routes, policies and API projections.

Actors are `Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor`. Background actors and principals both require the configured host policy. Public rendering accepts the explicit `PublicActor::Anonymous` marker. Services never read an ambient request or current principal.

Every native workflow reloads Eloquent actors through Core's canonical actor resolver before authorization, reference validation, revisions and events. Caller attributes and loaded relations cannot grant privileges. External identity providers retain their own freshness contract and require a nonnull identity. Owners are also reloaded through canonical registered storage, including ordinary unscoped hosts; bulk summaries reload once per owner class.

Placement reads that include block values, live rendering and snapshot capture additionally require `ContentAbility::View` for each distinct block and owner group. This supplements the composition/list permission. Keep policy callbacks query-free where possible; block checks are deduplicated within each owner group. Anonymous live rendering requires `publicOnly=true`, and anonymous snapshot rendering rejects snapshots containing private blocks. Authorized snapshot rendering remains independent of the live blocks.

Mutation arrays use native camelCase domain keys, including `scopeKey`, `parentId`, `sortOrder` and `isVisible`. Update and move item concurrency controls use `expected_revision`; lifecycle methods such as publish and delete take `$expectedRevision` separately. Enum values or their string values are accepted where validated. Optimistic revisions, eligibility, canonical owner checks, locale validation and after-commit events remain enforced.

`ContentReader::paginate` accepts only `key`, `scope`, `scope_key`, `status`, `visibility` and `definition_id`. Unknown keys and malformed scalar/enum/UUID values fail validation instead of silently widening the requested result.

## Owners and optional integrations

Register a host model through `ContentOwnerRegistrar::register($alias, $model, $groups)`. Plain Eloquent models must supply an explicit nonempty group list. Existing `ContentOwner` implementations may provide their own `contentGroups()`. Plain-model hard deletion must invoke `ContentOwnerDeletion::execute($owner, $deleteCallback)` to keep placement cleanup atomic; the optional `HasContent` trait provides the existing model hook.

Media is optional. Install `nvl/media`, bind its native authorization policy and set `nvl-content.media.enabled=true` to use Media fields and synchronized usages. Text-only content works with the adapter disabled. Host-supplied Blade views may be checked by enabling `rendering.strict_views`; the package ships no views, routes or view-publishing command.

Private Media projections pass the explicit actor into native signed-link issuance. Their lifetime is the shorter of Content's `media.private_url_ttl_minutes` and Media's per-disk maximum. The host supplies Media's named delivery routes; Content does not register endpoints.

Native Ownership is opt-in through declared model resources. Retired Tenancy bridge configuration and implicit schema conversion are not supported. Existing consumer storage is never deleted by this migration.

HTTP, editor bootstrap, DTO and Blade examples are active consumer-owned fixtures under `tests/Fixtures/Consumer`. Spatie Data is a development dependency for those fixtures only. See [event delivery](docs/events.md).

Shared [service contracts, model extensions and composition](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/consumer-api.md) apply alongside the package-specific rules above.
