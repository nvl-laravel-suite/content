# Public API: nvl/content

Generated from the package public contract. Use this reference to confirm fully qualified names, parameter order, defaults, native array shapes and return types. Read [usage](usage.md) for policy and operation semantics; declarations alone never grant authority.

Search for the relevant fully qualified service or contract and read only its matching symbol sections; this is a lookup catalog, not an instruction to load every declaration. `resolved` annotations expand imported aliases and domain type shapes. Framework inheritance and host-authored extensions remain outside this package contract.

## Symbols

- `Nvl\Content\Casts\ContentCompositionSnapshotCast`
- `Nvl\Content\Contracts\ContentAuthorization`
- `Nvl\Content\Contracts\ContentBlockQueryScope`
- `Nvl\Content\Contracts\ContentDefinitionMigration`
- `Nvl\Content\Contracts\ContentFieldDefinitionValidator`
- `Nvl\Content\Contracts\ContentFieldPreset`
- `Nvl\Content\Contracts\ContentFieldTypeAdapter`
- `Nvl\Content\Contracts\ContentOwner`
- `Nvl\Content\Contracts\ContentOwnerRegistrar`
- `Nvl\Content\Contracts\ContentReferenceResolver`
- `Nvl\Content\Contracts\ContentSnapshotIntegrity`
- `Nvl\Content\Contracts\ContentStorage`
- `Nvl\Content\Contracts\OwnershipSafeContentReferenceResolver`
- `Nvl\Content\Database\Factories\ContentBlockFactory`
- `Nvl\Content\Database\Factories\ContentBlockTranslationFactory`
- `Nvl\Content\Database\Factories\ContentDefinitionFactory`
- `Nvl\Content\Database\Factories\ContentPlacementFactory`
- `Nvl\Content\Database\Factories\ContentRevisionFactory`
- `Nvl\Content\Enums\ContentAbility`
- `Nvl\Content\Enums\ContentAlignment`
- `Nvl\Content\Enums\ContentHeadingLevel`
- `Nvl\Content\Enums\ContentLinkTarget`
- `Nvl\Content\Enums\ContentMutationMode`
- `Nvl\Content\Enums\ContentPlacementEvent`
- `Nvl\Content\Enums\ContentResponseCode`
- `Nvl\Content\Enums\ContentRevisionEvent`
- `Nvl\Content\Enums\ContentStatus`
- `Nvl\Content\Enums\ContentVisibility`
- `Nvl\Content\Events\ContentBlockChanged`
- `Nvl\Content\Events\ContentPlacementChanged`
- `Nvl\Content\Exceptions\ContentDefinitionCacheException`
- `Nvl\Content\Exceptions\ContentDefinitionMigrationException`
- `Nvl\Content\Exceptions\ContentDefinitionMigrationRequiredException`
- `Nvl\Content\Exceptions\ContentException`
- `Nvl\Content\Exceptions\ContentScopeOverflowException`
- `Nvl\Content\Exceptions\InvalidContentException`
- `Nvl\Content\Exceptions\StaleContentException`
- `Nvl\Content\Models\ContentBlock`
- `Nvl\Content\Models\ContentBlockTranslation`
- `Nvl\Content\Models\ContentDefinition`
- `Nvl\Content\Models\ContentPlacement`
- `Nvl\Content\Models\ContentRevision`
- `Nvl\Content\Schema\ContentDefinitionSource`
- `Nvl\Content\Schema\ContentFieldDefinition`
- `Nvl\Content\Schema\ContentSchema`
- `Nvl\Content\Services\ContentBlocks`
- `Nvl\Content\Services\ContentCatalogCopyRegistry`
- `Nvl\Content\Services\ContentDefinitionMigrationRegistry`
- `Nvl\Content\Services\ContentDefinitionRegistry`
- `Nvl\Content\Services\ContentDefinitions`
- `Nvl\Content\Services\ContentModelRegistry`
- `Nvl\Content\Services\ContentOwnerDeletion`
- `Nvl\Content\Services\ContentPlacements`
- `Nvl\Content\Services\ContentReader`
- `Nvl\Content\Services\ContentRenderResources`
- `Nvl\Content\Services\ContentRenderer`
- `Nvl\Content\Services\ContentSchemaCompiler`
- `Nvl\Content\Services\ContentScopeRegistry`
- `Nvl\Content\Services\ContentSnapshotIntegrity`
- `Nvl\Content\Traits\HasContent`
- `Nvl\Content\Validation\ContentValidationContext`

## Nvl\Content\Casts\ContentCompositionSnapshotCast

```text
final class Nvl\Content\Casts\ContentCompositionSnapshotCast implements Illuminate\Contracts\Database\Eloquent\CastsAttributes
@implements CastsAttributes<(array<string, mixed> | null), mixed>
resolved @implements Illuminate\Contracts\Database\Eloquent\CastsAttributes<(array<string, mixed> | null), mixed>
public function get(Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes): ?array
@return (array<string, mixed> | null)
public function set(Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes): array
@return array<string, (string | null)>
```

## Nvl\Content\Contracts\ContentAuthorization

```text
interface Nvl\Content\Contracts\ContentAuthorization
public abstract function authorize(Nvl\Content\Enums\ContentAbility $ability, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, ?Nvl\Content\Models\ContentBlock $block=null, ?Illuminate\Database\Eloquent\Model $owner=null, array $context=[]): void
@param array<string, mixed> $context
```

## Nvl\Content\Contracts\ContentBlockQueryScope

```text
interface Nvl\Content\Contracts\ContentBlockQueryScope
public abstract function scopeContentBlocks(Illuminate\Database\Eloquent\Builder $query, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): void
@param Builder<ContentBlock> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
```

## Nvl\Content\Contracts\ContentDefinitionMigration

```text
interface Nvl\Content\Contracts\ContentDefinitionMigration
public abstract function definitionKey(): string
public abstract function fromVersion(): int
public abstract function migrate(array $context): array
@param array{blockId: string, blockKey: string, scope: string, scopeKey: string, status: ContentStatus, visibility: ContentVisibility, fromVersion: int, toVersion: int, values: array<string, mixed>, translations: array<string, array<string, mixed>>, metadata: array<string, mixed>} $context
resolved @param array{blockId: string, blockKey: string, scope: string, scopeKey: string, status: Nvl\Content\Enums\ContentStatus, visibility: Nvl\Content\Enums\ContentVisibility, fromVersion: int, toVersion: int, values: array<string, mixed>, translations: array<string, array<string, mixed>>, metadata: array<string, mixed>} $context
@return array{values: array<string, mixed>, translations: array<string, array<string, mixed>>, metadata: array<string, mixed>}
public abstract function toVersion(): int
```

## Nvl\Content\Contracts\ContentFieldDefinitionValidator

```text
interface Nvl\Content\Contracts\ContentFieldDefinitionValidator
public abstract function validateDefinition(Nvl\Content\Schema\ContentFieldDefinition $field): void
```

## Nvl\Content\Contracts\ContentFieldPreset

```text
interface Nvl\Content\Contracts\ContentFieldPreset
public abstract function alias(): string
public abstract function definition(): array
@return array<string, mixed>
public abstract function description(): ?string
public abstract function jsonSchema(array $schema, Nvl\Content\Schema\ContentFieldDefinition $field): array
@param array<string, mixed> $schema
@return array<string, mixed>
public abstract function name(): string
public abstract function normalize(mixed $value, Nvl\Content\Schema\ContentFieldDefinition $field, Nvl\Content\Validation\ContentValidationContext $context): mixed
public abstract function render(mixed $value, Nvl\Content\Schema\ContentFieldDefinition $field, Nvl\Content\Validation\ContentValidationContext $context): mixed
public abstract function validate(mixed $value, Nvl\Content\Schema\ContentFieldDefinition $field, Nvl\Content\Validation\ContentValidationContext $context): void
```

## Nvl\Content\Contracts\ContentFieldTypeAdapter

```text
interface Nvl\Content\Contracts\ContentFieldTypeAdapter
public abstract function alias(): string
public abstract function normalize(mixed $value, Nvl\Content\Schema\ContentFieldDefinition $field, Nvl\Content\Validation\ContentValidationContext $context): mixed
public abstract function render(mixed $value, Nvl\Content\Schema\ContentFieldDefinition $field, Nvl\Content\Validation\ContentValidationContext $context): mixed
```

## Nvl\Content\Contracts\ContentOwner

```text
interface Nvl\Content\Contracts\ContentOwner
public abstract function contentGroups(): array
@return list<string>
```

## Nvl\Content\Contracts\ContentOwnerRegistrar

```text
interface Nvl\Content\Contracts\ContentOwnerRegistrar
public abstract function register(string $alias, string $model, ?array $groups=null): void
@param class-string<Model> $model
resolved @param class-string<Illuminate\Database\Eloquent\Model> $model
@param (array<array-key, mixed> | null) $groups
public abstract function registered(string $alias): ?string
@return (class-string<Model> | null)
resolved @return (class-string<Illuminate\Database\Eloquent\Model> | null)
```

## Nvl\Content\Contracts\ContentReferenceResolver

```text
interface Nvl\Content\Contracts\ContentReferenceResolver
public abstract function alias(): string
public abstract function display(string $identifier, Nvl\Content\Validation\ContentValidationContext $context): ?array
@return (array<string, mixed> | null)
public abstract function exists(string $identifier, Nvl\Content\Validation\ContentValidationContext $context): bool
```

## Nvl\Content\Contracts\ContentSnapshotIntegrity

```text
interface Nvl\Content\Contracts\ContentSnapshotIntegrity
public abstract function assertValid(array $snapshot): void
@param array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)} $snapshot
public abstract function digest(array $snapshot): string
@param array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)} $snapshot
public abstract function rebind(array $source, string $type, string $id, array $context, ?array $blocks=null): array
@param array<string, mixed> $context
@param (list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}> | null) $blocks
@param array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)} $source
@return array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)}
```

## Nvl\Content\Contracts\ContentStorage

```text
interface Nvl\Content\Contracts\ContentStorage
public abstract function blocks(): Illuminate\Database\Eloquent\Builder
@return Builder<ContentBlock>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public abstract function connection(): Illuminate\Database\Connection
public abstract function owned(): bool
```

## Nvl\Content\Contracts\OwnershipSafeContentReferenceResolver

```text
interface Nvl\Content\Contracts\OwnershipSafeContentReferenceResolver extends Nvl\Content\Contracts\ContentReferenceResolver
public abstract function alias(): string
public abstract function display(string $identifier, Nvl\Content\Validation\ContentValidationContext $context): ?array
@return (array<string, mixed> | null)
public abstract function exists(string $identifier, Nvl\Content\Validation\ContentValidationContext $context): bool
public abstract function ownershipRecord(string $identifier, Nvl\Content\Validation\ContentValidationContext $context): ?Illuminate\Database\Eloquent\Model
```

## Nvl\Content\Database\Factories\ContentBlockFactory

```text
final class Nvl\Content\Database\Factories\ContentBlockFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<ContentBlock>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Content\Models\ContentBlock>
public function definition(): array
@return array<model-property<ContentBlock>, mixed>
resolved @return array<model-property<Nvl\Content\Models\ContentBlock>, mixed>
public function forDefinition(Nvl\Content\Models\ContentDefinition $parent): static
public function modelName(): string
```

## Nvl\Content\Database\Factories\ContentBlockTranslationFactory

```text
final class Nvl\Content\Database\Factories\ContentBlockTranslationFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<ContentBlockTranslation>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Content\Models\ContentBlockTranslation>
public function definition(): array
@return array<model-property<ContentBlockTranslation>, mixed>
resolved @return array<model-property<Nvl\Content\Models\ContentBlockTranslation>, mixed>
public function forBlock(Nvl\Content\Models\ContentBlock $parent): static
```

## Nvl\Content\Database\Factories\ContentDefinitionFactory

```text
final class Nvl\Content\Database\Factories\ContentDefinitionFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<ContentDefinition>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Content\Models\ContentDefinition>
public function definition(): array
@return array<model-property<ContentDefinition>, mixed>
resolved @return array<model-property<Nvl\Content\Models\ContentDefinition>, mixed>
```

## Nvl\Content\Database\Factories\ContentPlacementFactory

```text
final class Nvl\Content\Database\Factories\ContentPlacementFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<ContentPlacement>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Content\Models\ContentPlacement>
public function definition(): array
@return array<model-property<ContentPlacement>, mixed>
resolved @return array<model-property<Nvl\Content\Models\ContentPlacement>, mixed>
public function forBlock(Nvl\Content\Models\ContentBlock $parent): static
public function forOwner(Illuminate\Database\Eloquent\Model $owner): static
```

## Nvl\Content\Database\Factories\ContentRevisionFactory

```text
final class Nvl\Content\Database\Factories\ContentRevisionFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<ContentRevision>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Content\Models\ContentRevision>
public function definition(): array
@return array<model-property<ContentRevision>, mixed>
resolved @return array<model-property<Nvl\Content\Models\ContentRevision>, mixed>
public function forBlock(Nvl\Content\Models\ContentBlock $parent): static
```

## Nvl\Content\Enums\ContentAbility

```text
enum Nvl\Content\Enums\ContentAbility implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentAbility Archive=Nvl\Content\Enums\ContentAbility::Archive="archive"
public const Nvl\Content\Enums\ContentAbility Create=Nvl\Content\Enums\ContentAbility::Create="create"
public const Nvl\Content\Enums\ContentAbility Delete=Nvl\Content\Enums\ContentAbility::Delete="delete"
public const Nvl\Content\Enums\ContentAbility List=Nvl\Content\Enums\ContentAbility::List="list"
public const Nvl\Content\Enums\ContentAbility ListDefinitions=Nvl\Content\Enums\ContentAbility::ListDefinitions="list_definitions"
public const Nvl\Content\Enums\ContentAbility ListPlacements=Nvl\Content\Enums\ContentAbility::ListPlacements="list_placements"
public const Nvl\Content\Enums\ContentAbility MigrateDefinitions=Nvl\Content\Enums\ContentAbility::MigrateDefinitions="migrate_definitions"
public const Nvl\Content\Enums\ContentAbility Place=Nvl\Content\Enums\ContentAbility::Place="place"
public const Nvl\Content\Enums\ContentAbility Publish=Nvl\Content\Enums\ContentAbility::Publish="publish"
public const Nvl\Content\Enums\ContentAbility Render=Nvl\Content\Enums\ContentAbility::Render="render"
public const Nvl\Content\Enums\ContentAbility Restore=Nvl\Content\Enums\ContentAbility::Restore="restore"
public const Nvl\Content\Enums\ContentAbility SyncDefinitions=Nvl\Content\Enums\ContentAbility::SyncDefinitions="sync_definitions"
public const Nvl\Content\Enums\ContentAbility Unplace=Nvl\Content\Enums\ContentAbility::Unplace="unplace"
public const Nvl\Content\Enums\ContentAbility Update=Nvl\Content\Enums\ContentAbility::Update="update"
public const Nvl\Content\Enums\ContentAbility View=Nvl\Content\Enums\ContentAbility::View="view"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentAlignment

```text
enum Nvl\Content\Enums\ContentAlignment implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentAlignment Center=Nvl\Content\Enums\ContentAlignment::Center="center"
public const Nvl\Content\Enums\ContentAlignment End=Nvl\Content\Enums\ContentAlignment::End="end"
public const Nvl\Content\Enums\ContentAlignment Start=Nvl\Content\Enums\ContentAlignment::Start="start"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentHeadingLevel

```text
enum Nvl\Content\Enums\ContentHeadingLevel implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentHeadingLevel H1=Nvl\Content\Enums\ContentHeadingLevel::H1="h1"
public const Nvl\Content\Enums\ContentHeadingLevel H2=Nvl\Content\Enums\ContentHeadingLevel::H2="h2"
public const Nvl\Content\Enums\ContentHeadingLevel H3=Nvl\Content\Enums\ContentHeadingLevel::H3="h3"
public const Nvl\Content\Enums\ContentHeadingLevel H4=Nvl\Content\Enums\ContentHeadingLevel::H4="h4"
public const Nvl\Content\Enums\ContentHeadingLevel H5=Nvl\Content\Enums\ContentHeadingLevel::H5="h5"
public const Nvl\Content\Enums\ContentHeadingLevel H6=Nvl\Content\Enums\ContentHeadingLevel::H6="h6"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentLinkTarget

```text
enum Nvl\Content\Enums\ContentLinkTarget implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentLinkTarget NewContext=Nvl\Content\Enums\ContentLinkTarget::NewContext="_blank"
public const Nvl\Content\Enums\ContentLinkTarget SameContext=Nvl\Content\Enums\ContentLinkTarget::SameContext="_self"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentMutationMode

```text
enum Nvl\Content\Enums\ContentMutationMode implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentMutationMode Patch=Nvl\Content\Enums\ContentMutationMode::Patch="patch"
public const Nvl\Content\Enums\ContentMutationMode Replace=Nvl\Content\Enums\ContentMutationMode::Replace="replace"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentPlacementEvent

```text
enum Nvl\Content\Enums\ContentPlacementEvent implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentPlacementEvent Created=Nvl\Content\Enums\ContentPlacementEvent::Created="created"
public const Nvl\Content\Enums\ContentPlacementEvent Deleted=Nvl\Content\Enums\ContentPlacementEvent::Deleted="deleted"
public const Nvl\Content\Enums\ContentPlacementEvent Updated=Nvl\Content\Enums\ContentPlacementEvent::Updated="updated"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentResponseCode

```text
enum Nvl\Content\Enums\ContentResponseCode implements BackedEnum, Nvl\Support\Contracts\ResponseCode, UnitEnum: string
public const Nvl\Content\Enums\ContentResponseCode DefinitionCacheInvalid=Nvl\Content\Enums\ContentResponseCode::DefinitionCacheInvalid="definition_cache_invalid"
public const Nvl\Content\Enums\ContentResponseCode DefinitionMigrationFailed=Nvl\Content\Enums\ContentResponseCode::DefinitionMigrationFailed="definition_migration_failed"
public const Nvl\Content\Enums\ContentResponseCode DefinitionMigrationRequired=Nvl\Content\Enums\ContentResponseCode::DefinitionMigrationRequired="definition_migration_required"
public const Nvl\Content\Enums\ContentResponseCode InvalidContent=Nvl\Content\Enums\ContentResponseCode::InvalidContent="invalid_content"
public const Nvl\Content\Enums\ContentResponseCode OperationFailed=Nvl\Content\Enums\ContentResponseCode::OperationFailed="operation_failed"
public const Nvl\Content\Enums\ContentResponseCode StaleContent=Nvl\Content\Enums\ContentResponseCode::StaleContent="stale_content"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentRevisionEvent

```text
enum Nvl\Content\Enums\ContentRevisionEvent implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentRevisionEvent Archived=Nvl\Content\Enums\ContentRevisionEvent::Archived="archived"
public const Nvl\Content\Enums\ContentRevisionEvent Created=Nvl\Content\Enums\ContentRevisionEvent::Created="created"
public const Nvl\Content\Enums\ContentRevisionEvent Deleted=Nvl\Content\Enums\ContentRevisionEvent::Deleted="deleted"
public const Nvl\Content\Enums\ContentRevisionEvent Migrated=Nvl\Content\Enums\ContentRevisionEvent::Migrated="migrated"
public const Nvl\Content\Enums\ContentRevisionEvent Published=Nvl\Content\Enums\ContentRevisionEvent::Published="published"
public const Nvl\Content\Enums\ContentRevisionEvent Restored=Nvl\Content\Enums\ContentRevisionEvent::Restored="restored"
public const Nvl\Content\Enums\ContentRevisionEvent Updated=Nvl\Content\Enums\ContentRevisionEvent::Updated="updated"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentStatus

```text
enum Nvl\Content\Enums\ContentStatus implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentStatus Archived=Nvl\Content\Enums\ContentStatus::Archived="archived"
public const Nvl\Content\Enums\ContentStatus Draft=Nvl\Content\Enums\ContentStatus::Draft="draft"
public const Nvl\Content\Enums\ContentStatus Published=Nvl\Content\Enums\ContentStatus::Published="published"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Enums\ContentVisibility

```text
enum Nvl\Content\Enums\ContentVisibility implements BackedEnum, UnitEnum: string
public const Nvl\Content\Enums\ContentVisibility Private=Nvl\Content\Enums\ContentVisibility::Private="private"
public const Nvl\Content\Enums\ContentVisibility Public=Nvl\Content\Enums\ContentVisibility::Public="public"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Content\Events\ContentBlockChanged

```text
final class Nvl\Content\Events\ContentBlockChanged implements Nvl\Support\Contracts\DomainEvent, Nvl\Support\Ownership\Contracts\OwnershipQueuedJob
public function __construct(string $blockId, Nvl\Content\Enums\ContentRevisionEvent $event, int $revision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, int $schemaVersion=1): mixed
public function ownershipJobEnvelope(): Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope
public function schemaVersion(): int
public readonly array $actor
@var array{type: string, id: mixed, system: bool}
public readonly string $blockId
public readonly Nvl\Content\Enums\ContentRevisionEvent $event
public readonly int $revision
public readonly int $schemaVersion
```

## Nvl\Content\Events\ContentPlacementChanged

```text
final class Nvl\Content\Events\ContentPlacementChanged implements Nvl\Support\Contracts\DomainEvent, Nvl\Support\Ownership\Contracts\OwnershipQueuedJob
public function __construct(string $placementId, Nvl\Content\Enums\ContentPlacementEvent $event, int $revision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, ?string $ownerType=null, ?string $ownerId=null, ?string $group=null, ?string $blockId=null, int $schemaVersion=1): mixed
public function ownershipJobEnvelope(): Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope
public function schemaVersion(): int
public readonly array $actor
@var array{type: string, id: mixed, system: bool}
public readonly ?string $blockId
public readonly Nvl\Content\Enums\ContentPlacementEvent $event
public readonly ?string $group
public readonly ?string $ownerId
public readonly ?string $ownerType
public readonly string $placementId
public readonly int $revision
public readonly int $schemaVersion
```

## Nvl\Content\Exceptions\ContentDefinitionCacheException

```text
final class Nvl\Content\Exceptions\ContentDefinitionCacheException extends Nvl\Content\Exceptions\ContentException
public function __construct(string $message="", ?Nvl\Support\Contracts\ResponseCode $responseCode=null, int $suggestedStatus=422, array $publicContext=[], array $diagnosticContext=[], ?Throwable $previous=null): mixed
@param string $message
@param (ResponseCode | null) $responseCode
resolved @param (Nvl\Support\Contracts\ResponseCode | null) $responseCode
@param int $suggestedStatus
@param array<string, mixed> $publicContext
@param array<string, mixed> $diagnosticContext
@param (Throwable | null) $previous
@throws SupportException
resolved @throws Nvl\Support\Exceptions\SupportException
public function context(): array
@return array<string, mixed>
public static function invalid(string $reason, ?Throwable $previous=null): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
@return (string | null)
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
@return int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Content\Exceptions\ContentDefinitionMigrationException

```text
final class Nvl\Content\Exceptions\ContentDefinitionMigrationException extends Nvl\Content\Exceptions\ContentException
public function __construct(string $message="", ?Nvl\Support\Contracts\ResponseCode $responseCode=null, int $suggestedStatus=422, array $publicContext=[], array $diagnosticContext=[], ?Throwable $previous=null): mixed
@param string $message
@param (ResponseCode | null) $responseCode
resolved @param (Nvl\Support\Contracts\ResponseCode | null) $responseCode
@param int $suggestedStatus
@param array<string, mixed> $publicContext
@param array<string, mixed> $diagnosticContext
@param (Throwable | null) $previous
@throws SupportException
resolved @throws Nvl\Support\Exceptions\SupportException
public function context(): array
@return array<string, mixed>
public static function forStep(string $blockId, string $definition, Nvl\Content\Contracts\ContentDefinitionMigration $migration, Throwable $previous): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
@return (string | null)
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
@return int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Content\Exceptions\ContentDefinitionMigrationRequiredException

```text
final class Nvl\Content\Exceptions\ContentDefinitionMigrationRequiredException extends Nvl\Content\Exceptions\ContentException
public function __construct(string $message="", ?Nvl\Support\Contracts\ResponseCode $responseCode=null, int $suggestedStatus=422, array $publicContext=[], array $diagnosticContext=[], ?Throwable $previous=null): mixed
@param string $message
@param (ResponseCode | null) $responseCode
resolved @param (Nvl\Support\Contracts\ResponseCode | null) $responseCode
@param int $suggestedStatus
@param array<string, mixed> $publicContext
@param array<string, mixed> $diagnosticContext
@param (Throwable | null) $previous
@throws SupportException
resolved @throws Nvl\Support\Exceptions\SupportException
public function context(): array
@return array<string, mixed>
public static function forBlock(string $blockId, string $definition, int $storedVersion, int $currentVersion): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
@return (string | null)
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
@return int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Content\Exceptions\ContentException

```text
class Nvl\Content\Exceptions\ContentException extends Nvl\Support\Exceptions\BusinessException
public function __construct(string $message="", ?Nvl\Support\Contracts\ResponseCode $responseCode=null, int $suggestedStatus=422, array $publicContext=[], array $diagnosticContext=[], ?Throwable $previous=null): mixed
@param string $message
@param (ResponseCode | null) $responseCode
resolved @param (Nvl\Support\Contracts\ResponseCode | null) $responseCode
@param int $suggestedStatus
@param array<string, mixed> $publicContext
@param array<string, mixed> $diagnosticContext
@param (Throwable | null) $previous
@throws SupportException
resolved @throws Nvl\Support\Exceptions\SupportException
public function context(): array
@return array<string, mixed>
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
@return (string | null)
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
@return int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Content\Exceptions\ContentScopeOverflowException

```text
final class Nvl\Content\Exceptions\ContentScopeOverflowException extends Nvl\Content\Exceptions\ContentException
public function __construct(string $message="", ?Nvl\Support\Contracts\ResponseCode $responseCode=null, int $suggestedStatus=422, array $publicContext=[], array $diagnosticContext=[], ?Throwable $previous=null): mixed
@param string $message
@param (ResponseCode | null) $responseCode
resolved @param (Nvl\Support\Contracts\ResponseCode | null) $responseCode
@param int $suggestedStatus
@param array<string, mixed> $publicContext
@param array<string, mixed> $diagnosticContext
@param (Throwable | null) $previous
@throws SupportException
resolved @throws Nvl\Support\Exceptions\SupportException
public function context(): array
@return array<string, mixed>
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
@return (string | null)
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
@return int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Content\Exceptions\InvalidContentException

```text
final class Nvl\Content\Exceptions\InvalidContentException extends Nvl\Content\Exceptions\ContentException
public function __construct(string $message="", ?Nvl\Support\Contracts\ResponseCode $responseCode=null, int $suggestedStatus=422, array $publicContext=[], array $diagnosticContext=[], ?Throwable $previous=null): mixed
@param string $message
@param (ResponseCode | null) $responseCode
resolved @param (Nvl\Support\Contracts\ResponseCode | null) $responseCode
@param int $suggestedStatus
@param array<string, mixed> $publicContext
@param array<string, mixed> $diagnosticContext
@param (Throwable | null) $previous
@throws SupportException
resolved @throws Nvl\Support\Exceptions\SupportException
public function context(): array
@return array<string, mixed>
public static function fromInvalidArgument(InvalidArgumentException $exception): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
@return (string | null)
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
@return int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Content\Exceptions\StaleContentException

```text
final class Nvl\Content\Exceptions\StaleContentException extends Nvl\Content\Exceptions\ContentException
public function __construct(string $message="", ?Nvl\Support\Contracts\ResponseCode $responseCode=null, int $suggestedStatus=422, array $publicContext=[], array $diagnosticContext=[], ?Throwable $previous=null): mixed
@param string $message
@param (ResponseCode | null) $responseCode
resolved @param (Nvl\Support\Contracts\ResponseCode | null) $responseCode
@param int $suggestedStatus
@param array<string, mixed> $publicContext
@param array<string, mixed> $diagnosticContext
@param (Throwable | null) $previous
@throws SupportException
resolved @throws Nvl\Support\Exceptions\SupportException
public function context(): array
@return array<string, mixed>
public static function forRevision(string $resourceId, int $expected, int $actual): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
@return (string | null)
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
@return int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Content\Models\ContentBlock

```text
class Nvl\Content\Models\ContentBlock extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable, Nvl\Translatable\Contracts\TranslatableModel
public const string OWNERSHIP_RESOURCE="content.blocks"
public static function allowsCentralCatalog(): bool
public function definition(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<ContentDefinition, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Content\Models\ContentDefinition, $this>
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getAllTranslations(): Illuminate\Database\Eloquent\Collection
@return Collection<int, Model>
resolved @return Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Model>
public function getAvailableLocales(): array
@return list<string>
public function getConnectionName(): ?string
public function getCurrentLocale(): string
public function getRelationValue(mixed $key): mixed
@param string $key
public function getTable(): string
public function getTranslatedAttributes(?string $locale=null): array
@return array<string, mixed>
public function getTranslation(?string $locale=null, bool $withFallback=true): ?Illuminate\Database\Eloquent\Model
public function hasTranslation(?string $locale=null): bool
public function isTranslatableAttribute(string $key): bool
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function placements(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<ContentPlacement, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Content\Models\ContentPlacement, $this>
public function refresh(): static
public function refreshForUpdate(): static
public function resolveRouteBinding(mixed $value, mixed $field=null): ?Nvl\Content\Models\ContentBlock
@param mixed $value
@param (string | null) $field
public function resolveSoftDeletableRouteBinding(mixed $value, mixed $field=null): ?Nvl\Content\Models\ContentBlock
@param mixed $value
@param (string | null) $field
public function resolveTranslation(string $field, ?string $locale=null): Nvl\Translatable\TranslationResolution
public function revisions(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<ContentRevision, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Content\Models\ContentRevision, $this>
public function save(array $options=[]): bool
@param array<string, mixed> $options
public function scopeOrderByTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, string $direction="asc", ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public function scopePublic(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
public function scopePublished(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
public function scopeWhereTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, mixed $operator, mixed $value=\Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public function scopeWhereTranslationNotNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public function scopeWhereTranslationNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public function scopeWithAllTranslations(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public function scopeWithResolvedTranslations(Illuminate\Database\Eloquent\Builder $query, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock>
public function setLocale(string $locale): self
public function translated(string $field, ?string $locale=null): mixed
public function translation(?string $locale=null): Illuminate\Database\Eloquent\Relations\HasOne
@return HasOne<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasOne<Illuminate\Database\Eloquent\Model, $this>
public function translationDefinition(): Nvl\Translatable\RelatedTranslationDefinition
public function translationResourceKey(): int|string
public function translations(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Illuminate\Database\Eloquent\Model, $this>
@method static Builder<static> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
@method static Builder<static> whereTranslated(string $field, mixed $operator, mixed $value = TranslationComparisonValue::Omitted, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> whereTranslated(string $field, mixed $operator, mixed $value = Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale = null)
@method static Builder<static> whereTranslationNotNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> whereTranslationNotNull(string $field, ?string $locale = null)
@method static Builder<static> whereTranslationNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> whereTranslationNull(string $field, ?string $locale = null)
@method static Builder<static> withAllTranslations()
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> withAllTranslations()
@method static Builder<static> withResolvedTranslations(?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlock> withResolvedTranslations(?string $locale = null)
@property (string | null) $created_by_id
@property (string | null) $created_by_type
@property-read ContentDefinition $definition
resolved @property-read Nvl\Content\Models\ContentDefinition $definition
@property string $definition_hash
@property string $definition_id
@property ContentSchema $definition_schema
resolved @property Nvl\Content\Schema\ContentSchema $definition_schema
@property int $definition_version
@property (string | null) $definition_view
@property string $id
@property string $key
@property (array<string, mixed> | null) $metadata
@property-read Collection<int, ContentPlacement> $placements
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Content\Models\ContentPlacement> $placements
@property (Carbon | null) $published_at
resolved @property (Illuminate\Support\Carbon | null) $published_at
@property (string | null) $published_by_id
@property (string | null) $published_by_type
@property int $revision
@property-read Collection<int, ContentRevision> $revisions
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Content\Models\ContentRevision> $revisions
@property string $scope
@property string $scope_key
@property ContentStatus $status
resolved @property Nvl\Content\Enums\ContentStatus $status
@property-read Collection<int, ContentBlockTranslation> $translations
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Content\Models\ContentBlockTranslation> $translations
@property (string | null) $updated_by_id
@property (string | null) $updated_by_type
@property (array<string, mixed> | null) $values
@property ContentVisibility $visibility
resolved @property Nvl\Content\Enums\ContentVisibility $visibility
```

## Nvl\Content\Models\ContentBlockTranslation

```text
final class Nvl\Content\Models\ContentBlockTranslation extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="content.translations"
public function block(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<ContentBlock, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Content\Models\ContentBlock, $this>
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentBlockTranslation>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property-read ContentBlock $block
resolved @property-read Nvl\Content\Models\ContentBlock $block
@property string $content_block_id
@property string $id
@property string $locale
@property array<string, mixed> $values
```

## Nvl\Content\Models\ContentDefinition

```text
final class Nvl\Content\Models\ContentDefinition extends Illuminate\Database\Eloquent\Model
public function blocks(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<ContentBlock, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Content\Models\ContentBlock, $this>
public function getConnectionName(): ?string
public function getTable(): string
@property (list<string> | null) $allowed_regions
@property (list<string> | null) $allowed_scopes
@property-read Collection<int, ContentBlock> $blocks
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Content\Models\ContentBlock> $blocks
@property string $category
@property (array<string, mixed> | null) $defaults
@property (string | null) $description
@property string $id
@property bool $is_active
@property string $key
@property string $name
@property (Carbon | null) $orphaned_at
resolved @property (Illuminate\Support\Carbon | null) $orphaned_at
@property ContentSchema $schema
resolved @property Nvl\Content\Schema\ContentSchema $schema
@property int $sort_order
@property string $source_hash
@property (Carbon | null) $synced_at
resolved @property (Illuminate\Support\Carbon | null) $synced_at
@property int $version
@property (string | null) $view
```

## Nvl\Content\Models\ContentPlacement

```text
final class Nvl\Content\Models\ContentPlacement extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string DEFAULT_GROUP="default"
public const string OWNERSHIP_RESOURCE="content.placements"
public function block(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<ContentBlock, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Content\Models\ContentBlock, $this>
public function children(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<ContentPlacement, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Content\Models\ContentPlacement, $this>
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentPlacement>
public function owner(): Illuminate\Database\Eloquent\Relations\MorphTo
@return MorphTo<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\MorphTo<Illuminate\Database\Eloquent\Model, $this>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function parent(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<ContentPlacement, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Content\Models\ContentPlacement, $this>
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property-read ContentBlock $block
resolved @property-read Nvl\Content\Models\ContentBlock $block
@property-read Collection<int, ContentPlacement> $children
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Content\Models\ContentPlacement> $children
@property string $content_block_id
@property string $group
@property string $id
@property bool $is_visible
@property string $key
@property (array<string, mixed> | null) $overrides
@property-read Model $owner
resolved @property-read Illuminate\Database\Eloquent\Model $owner
@property string $owner_id
@property string $owner_type
@property-read (ContentPlacement | null) $parent
resolved @property-read (Nvl\Content\Models\ContentPlacement | null) $parent
@property (string | null) $parent_id
@property string $region
@property int $revision
@property int $sort_order
```

## Nvl\Content\Models\ContentRevision

```text
final class Nvl\Content\Models\ContentRevision extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="content.revisions"
public function block(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<ContentBlock, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Content\Models\ContentBlock, $this>
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Content\Models\ContentRevision>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property (string | null) $actor_id
@property (string | null) $actor_type
@property-read ContentBlock $block
resolved @property-read Nvl\Content\Models\ContentBlock $block
@property string $content_block_id
@property ContentRevisionEvent $event
resolved @property Nvl\Content\Enums\ContentRevisionEvent $event
@property string $id
@property int $revision
@property array<string, mixed> $snapshot
```

## Nvl\Content\Schema\ContentDefinitionSource

```text
final readonly class Nvl\Content\Schema\ContentDefinitionSource
public function __construct(string $key, string $name, ?string $description, string $category, int $version, ?string $view, array $schema, array $defaults=[], array $allowedScopes=['global'], array $allowedRegions=['main'], bool $isActive=true, int $sortOrder=0): mixed
@param array<array-key, mixed> $schema
@param array<string, mixed> $defaults
@param list<string> $allowedScopes
@param list<string> $allowedRegions
public readonly array $allowedRegions
@param list<string> $allowedRegions
public readonly array $allowedScopes
@param list<string> $allowedScopes
public readonly string $category
public readonly array $defaults
@param array<string, mixed> $defaults
public readonly ?string $description
public readonly bool $isActive
public readonly string $key
public readonly string $name
public readonly array $schema
@param array<array-key, mixed> $schema
public readonly int $sortOrder
public readonly int $version
public readonly ?string $view
```

## Nvl\Content\Schema\ContentFieldDefinition

```text
final readonly class Nvl\Content\Schema\ContentFieldDefinition
public function __construct(string $key, string $type, string $label, bool $required=false, bool $localized=false, mixed $default=null, array $fields=[], ?self $item=null, array $settings=[], ?string $preset=null): mixed
@param list<ContentFieldDefinition> $fields
resolved @param list<Nvl\Content\Schema\ContentFieldDefinition> $fields
@param array<string, mixed> $settings
public static function fromArray(array $definition): self
@param array<string, mixed> $definition
public function setting(string $key, mixed $default=null): mixed
public function toArray(): array
@return array<string, mixed>
public readonly mixed $default
public readonly array $fields
@param list<ContentFieldDefinition> $fields
resolved @param list<Nvl\Content\Schema\ContentFieldDefinition> $fields
public readonly ?self $item
public readonly string $key
public readonly string $label
public readonly bool $localized
public readonly ?string $preset
public readonly bool $required
public readonly array $settings
@param array<string, mixed> $settings
public readonly string $type
```

## Nvl\Content\Schema\ContentSchema

```text
final readonly class Nvl\Content\Schema\ContentSchema implements JsonSerializable
public function __construct(array $fields): mixed
@param list<ContentFieldDefinition> $fields
resolved @param list<Nvl\Content\Schema\ContentFieldDefinition> $fields
public function fieldCount(): int
public function fieldTypes(): array
@return array<string, string>
public static function fromArray(array $schema): self
@param array<array-key, mixed> $schema
public function get(string $key): ?Nvl\Content\Schema\ContentFieldDefinition
public function jsonSerialize(): array
@return array{fields: list<array<string, mixed>>}
public function toArray(): array
@return array{fields: list<array<string, mixed>>}
public readonly array $fields
@param list<ContentFieldDefinition> $fields
resolved @param list<Nvl\Content\Schema\ContentFieldDefinition> $fields
```

## Nvl\Content\Services\ContentBlocks

```text
final class Nvl\Content\Services\ContentBlocks
public function __construct(Nvl\Support\Auth\CanonicalActorResolver $actors, Nvl\Content\Contracts\ContentAuthorization $authorization, Nvl\Content\Services\ContentDefinitionRegistry $definitions, Nvl\Content\Services\ContentScopeRegistry $scopes, Nvl\Content\Validation\ContentValueValidator $validator, Nvl\Content\Services\ContentMediaSynchronizer $media, Nvl\Content\Services\ContentRevisionRecorder $revisions, Nvl\Content\Services\CanonicalJson $json, Nvl\Content\Services\ContentPayloadGuard $guard, Nvl\Content\Services\ContentPatch $patch, Nvl\Content\Services\ContentIdentityGuard $identities, Nvl\Translatable\Services\RelatedTranslationStore $translations, Nvl\Support\Events\DomainEventDispatcher $domainEvents, Nvl\Content\Services\ContentDefinitionVersionGuard $definitionVersions, Nvl\Translatable\Services\RelatedTranslationStore $translationWriter, Nvl\Content\Services\ContentBlockPlacementSynchronizer $placements): mixed
public function archive(Nvl\Content\Models\ContentBlock|string $block, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentBlock
public function create(array $data, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentBlock
@param array{definition: string, key: string, scope?: string, scopeKey?: string, visibility?: (ContentVisibility | string), values?: array<string, mixed>, translations?: array<string, array<string, mixed>>, metadata?: array<string, mixed>} $data
resolved @param array{definition: string, key: string, scope?: string, scopeKey?: string, visibility?: (Nvl\Content\Enums\ContentVisibility | string), values?: array<string, mixed>, translations?: array<string, array<string, mixed>>, metadata?: array<string, mixed>} $data
public function delete(Nvl\Content\Models\ContentBlock|string $block, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): void
public function publish(Nvl\Content\Models\ContentBlock|string $block, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentBlock
public function restore(Nvl\Content\Models\ContentBlock|string $block, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentBlock
public function update(Nvl\Content\Models\ContentBlock|string $block, array $data, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentBlock
@param array{expected_revision: int, mode?: (ContentMutationMode | string), visibility?: (ContentVisibility | string | null), values?: array<string, mixed>, translations?: array<string, array<string, mixed>>, metadata?: array<string, mixed>} $data
resolved @param array{expected_revision: int, mode?: (Nvl\Content\Enums\ContentMutationMode | string), visibility?: (Nvl\Content\Enums\ContentVisibility | string | null), values?: array<string, mixed>, translations?: array<string, array<string, mixed>>, metadata?: array<string, mixed>} $data
@throws StaleContentException
resolved @throws Nvl\Content\Exceptions\StaleContentException
```

## Nvl\Content\Services\ContentCatalogCopyRegistry

```text
final class Nvl\Content\Services\ContentCatalogCopyRegistry
public function __construct(Illuminate\Contracts\Container\Container $container): mixed
public function register(string $ownerAlias, string $accessClass): void
public function resolve(string $ownerAlias): Nvl\Content\Contracts\ContentCatalogCopyAccess
```

## Nvl\Content\Services\ContentDefinitionMigrationRegistry

```text
final class Nvl\Content\Services\ContentDefinitionMigrationRegistry
public function hasPath(string $definition, int $from, int $to): bool
public function identifiers(): array
@return list<string>
public function path(string $definition, int $from, int $to): array
@return list<ContentDefinitionMigration>
resolved @return list<Nvl\Content\Contracts\ContentDefinitionMigration>
public function register(Nvl\Content\Contracts\ContentDefinitionMigration $migration): void
```

## Nvl\Content\Services\ContentDefinitionRegistry

```text
final class Nvl\Content\Services\ContentDefinitionRegistry
public function __construct(Nvl\Content\Validation\ContentSchemaValidator $validator, Nvl\Content\Services\ContentScopeRegistry $scopes, Nvl\Content\Services\ContentSchemaCompiler $compiler, Nvl\Content\Services\ContentJsonSchemaBuilder $jsonSchemas, Nvl\Content\Validation\ContentValueValidator $values): mixed
public function all(): array
@return list<array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>}>
resolved @return list<array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: Nvl\Content\Schema\ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>}>
public function get(string $key): array
@return array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>}
resolved @return array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: Nvl\Content\Schema\ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>}
public function register(Nvl\Content\Schema\ContentDefinitionSource $source): void
public function restoreCompiled(array $definitions): void
@param array<array-key, mixed> $definitions
```

## Nvl\Content\Services\ContentDefinitions

```text
final class Nvl\Content\Services\ContentDefinitions
public function __construct(Nvl\Support\Auth\CanonicalActorResolver $actors, Nvl\Content\Contracts\ContentAuthorization $authorization, Nvl\Content\Services\ContentDefinitionRegistry $definitions, Nvl\Content\Services\ContentDefinitionMigrationRegistry $migrations, Nvl\Content\Services\ContentDefinitionSyncLock $syncLock, Nvl\Content\Services\CanonicalJson $json, Nvl\Content\Services\ContentPlatformCatalogGuard $platformCatalog, Nvl\Content\Services\ContentCodeCatalogUsage $catalogUsage, Nvl\Content\Services\ContentBlockDefinitionMigrator $migrator, Nvl\Content\Services\ContentFieldPresetRegistry $presets, Nvl\Content\Services\ContentSchemaCompiler $compiler, Nvl\Content\Services\ContentJsonSchemaBuilder $jsonSchemas): mixed
public function all(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Illuminate\Support\Collection
@return Collection<int, covariant array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>}>
resolved @return Illuminate\Support\Collection<int, covariant array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: Nvl\Content\Schema\ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>}>
public function migrate(array $plan, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): array
@param array{definition: (string | null), limit: int, totalPending: int, hasMore: bool, ready: list<array{blockId: string, blockKey: string, definition: string, fromVersion: int, toVersion: int, expected_revision: int, deleted: bool, versions: list<int>}>, blocked: list<array{blockId: string, blockKey: string, definition: string, fromVersion: int, toVersion: int, expected_revision: int, deleted: bool, code: string, message: string}>} $plan
@return array{applied: bool, migrated: list<array{blockId: string, definition: string, fromVersion: int, toVersion: int, previousRevision: int, revision: int}>}
public function planMigration(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, ?string $definition=null, ?int $limit=null): array
@return array{definition: (string | null), limit: int, totalPending: int, hasMore: bool, ready: list<array{blockId: string, blockKey: string, definition: string, fromVersion: int, toVersion: int, expected_revision: int, deleted: bool, versions: list<int>}>, blocked: list<array{blockId: string, blockKey: string, definition: string, fromVersion: int, toVersion: int, expected_revision: int, deleted: bool, code: string, message: string}>}
public function presets(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Illuminate\Support\Collection
@return Collection<int, array{alias: string, name: string, description: (string | null), field: ContentFieldDefinition, jsonSchema: array<string, mixed>}>
resolved @return Illuminate\Support\Collection<int, array{alias: string, name: string, description: (string | null), field: Nvl\Content\Schema\ContentFieldDefinition, jsonSchema: array<string, mixed>}>
public function sync(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, bool $dryRun=false): array
@return array{create: list<string>, update: list<string>, unchanged: list<string>, orphan: list<string>, applied: bool}
```

## Nvl\Content\Services\ContentModelRegistry

```text
final class Nvl\Content\Services\ContentModelRegistry
public function __construct(Illuminate\Contracts\Config\Repository $configuration, Nvl\Support\OwnerRegistry $identities): mixed
public function blockClass(): string
@return class-string<ContentBlock>
resolved @return class-string<Nvl\Content\Models\ContentBlock>
public static function projectionColumns(array $columns): array
@param list<string> $columns
@return list<string>
```

## Nvl\Content\Services\ContentOwnerDeletion

```text
final readonly class Nvl\Content\Services\ContentOwnerDeletion
public function __construct(Nvl\Content\Services\ContentOwnerRegistry $owners, Nvl\Content\Services\ContentIdentityGuard $identities, Nvl\Content\Services\ContentPlacementOwnerLock $locks, Nvl\Content\Services\ContentMediaSynchronizer $media, Nvl\Support\Ownership\Contracts\OwnershipModelPersistence $persistence): mixed
public function execute(Illuminate\Database\Eloquent\Model $owner, Closure $delete): ?bool
@param Closure(): (bool | null) $delete
```

## Nvl\Content\Services\ContentPlacements

```text
final class Nvl\Content\Services\ContentPlacements
public function __construct(Nvl\Support\Auth\CanonicalActorResolver $actors, Nvl\Content\Contracts\ContentAuthorization $authorization, Nvl\Content\Services\ContentOwnerRegistry $owners, Nvl\Content\Services\ContentPlacementValidator $validator, Nvl\Content\Services\ContentIdentityGuard $identities, Nvl\Content\Services\ContentPlacementOwnerLock $ownerLocks, Nvl\Content\Services\ContentMediaSynchronizer $media, Nvl\Support\Events\DomainEventDispatcher $domainEvents, Nvl\Content\Services\ContentPlacementTree $tree): mixed
public function delete(Nvl\Content\Models\ContentPlacement|string $placement, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): void
public function move(Illuminate\Database\Eloquent\Model $owner, string $group, array $data, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): array
@param array{placements: list<array{id: string, expected_revision: int, region: string, parentId?: (string | null), sortOrder: int}>} $data
@return array<int, ContentPlacement>
resolved @return array<int, Nvl\Content\Models\ContentPlacement>
public function place(Nvl\Content\Models\ContentBlock|string $block, Illuminate\Database\Eloquent\Model $owner, string $group, array $data, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentPlacement
@param array{key: string, region?: string, parentId?: (string | null), sortOrder?: int, isVisible?: bool, overrides?: array<string, mixed>} $data
public function replace(Illuminate\Database\Eloquent\Model $owner, string $group, string $placement, string $block, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentPlacement
public function update(Nvl\Content\Models\ContentPlacement|string $placement, array $data, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentPlacement
@param array{expected_revision: int, region: string, parentId?: (string | null), sortOrder: int, isVisible: bool, overrides?: array<string, mixed>} $data
```

## Nvl\Content\Services\ContentReader

```text
final class Nvl\Content\Services\ContentReader
public function __construct(Nvl\Support\Auth\CanonicalActorResolver $actors, Nvl\Content\Contracts\ContentAuthorization $authorization, Nvl\Content\Services\ContentIdentityGuard $identities, Nvl\Content\Services\ContentOwnerRegistry $owners, Nvl\Support\Ownership\Contracts\OwnershipBoundary $boundary, Nvl\Content\Services\ContentLocalePolicy $locales, Nvl\Content\Services\ContentLocalizedValues $localizedValues, Nvl\Content\Services\ContentCatalogCopyRegistry $copies, Nvl\Content\Services\ContentMediaReferences $media, Nvl\Content\Services\ContentSnapshotIntegrity $integrity): mixed
public function composition(Illuminate\Database\Eloquent\Model $owner, array $context=[]): array
@param array{actor?: (Authenticatable | SystemActor), group?: string, locale?: string, publicOnly?: bool} $context
resolved @param array{actor?: (Illuminate\Contracts\Auth\Authenticatable | Nvl\Support\Enums\SystemActor), group?: string, locale?: string, publicOnly?: bool} $context
@return array<string, mixed>
public function exportForCopy(string $ownerAlias, string $ownerId, string $group, string $grantId): array
@return array{ownerAlias: string, ownerId: string, group: string, grantId: string, sourceRevision: int, sourceHash: string, snapshot: array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)}, mediaIds: list<string>}
public function exportSnapshot(Illuminate\Database\Eloquent\Model $owner, string $group, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): array
@return array<string, mixed>
public function find(string $key, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentBlock
public function get(Nvl\Content\Models\ContentBlock|string $block, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentBlock
public function groups(Illuminate\Database\Eloquent\Model $owner, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Illuminate\Support\Collection
@return Collection<int, string>
resolved @return Illuminate\Support\Collection<int, string>
public function importSnapshot(Illuminate\Database\Eloquent\Model $target, array $source, array $mediaMap, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): array
@param array<string, string> $mediaMap
@param array{ownerAlias: string, ownerId: string, group: string, grantId: string, sourceRevision: int, sourceHash: string, snapshot: array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)}, mediaIds: list<string>} $source
@return array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)}
public function paginate(array $filters, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, int $perPage=25): Illuminate\Contracts\Pagination\LengthAwarePaginator
@param array{key?: string, scope?: string, scope_key?: string, status?: (ContentStatus | string), visibility?: (ContentVisibility | string), definition_id?: string} $filters
resolved @param array{key?: string, scope?: string, scope_key?: string, status?: (Nvl\Content\Enums\ContentStatus | string), visibility?: (Nvl\Content\Enums\ContentVisibility | string), definition_id?: string} $filters
@return LengthAwarePaginator<int, ContentBlock>
resolved @return Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Nvl\Content\Models\ContentBlock>
public function placement(Illuminate\Database\Eloquent\Model $owner, string $group, string $idOrKey, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Content\Models\ContentPlacement
public function placements(Illuminate\Database\Eloquent\Model $owner, string $group, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, bool $includeBlocks=false): Illuminate\Support\Collection
@return Collection<int, ContentPlacement>
resolved @return Illuminate\Support\Collection<int, Nvl\Content\Models\ContentPlacement>
public function resolveScopes(array $scopes, string $locale, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, ?int $limit=null, bool $publicOnly=true): array
@param list<array{scope: string, scopeKey: string}> $scopes
@return array{locale: string, scopes: list<array{scope: string, scopeKey: string}>, values: array<string, array<string, mixed>>, sources: array<string, string>, matched: int, limit: int}
public function summaries(iterable $owners, string $group, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): array
@param iterable<array-key, Model> $owners
resolved @param iterable<array-key, Illuminate\Database\Eloquent\Model> $owners
@return array<string, list<ContentPlacement>>
resolved @return array<string, list<Nvl\Content\Models\ContentPlacement>>
```

## Nvl\Content\Services\ContentRenderResources

```text
final class Nvl\Content\Services\ContentRenderResources
public function reference(string $key, Closure $resolver): ?array
@param Closure(): (array<string, mixed> | null) $resolver
@return (array<string, mixed> | null)
```

## Nvl\Content\Services\ContentRenderer

```text
final readonly class Nvl\Content\Services\ContentRenderer
public function __construct(Nvl\Support\Auth\CanonicalActorResolver $actors, Nvl\Content\Contracts\ContentAuthorization $authorization, Nvl\Content\Services\ContentOwnerRegistry $owners, Nvl\Content\Validation\ContentValueValidator $values, Nvl\Content\Services\ContentPatch $patch, Nvl\Content\Services\CanonicalJson $json, Illuminate\Contracts\View\Factory $views, Nvl\Content\Services\ContentPlacementTree $tree, Nvl\Content\Services\ContentMediaReferences $mediaReferences, Nvl\Content\Services\ContentLocalePolicy $locales, Nvl\Content\Services\ContentLocalizedValues $localizedValues): mixed
public function render(Illuminate\Database\Eloquent\Model $owner, array $context=[]): array
@param array<string, mixed> $context
@return array{ownerType: string, ownerId: string, group: string, locale: string, blocks: list<array{id: string, placementId: string, definitionKey: string, key: (string | null), region: string, sortOrder: int, view: string, locale: string, values: array<string, mixed>, fieldTypes: array<string, string>, children: list<array<string, mixed>>}>, regions: array<string, list<array{id: string, placementId: string, definitionKey: string, key: (string | null), region: string, sortOrder: int, view: string, locale: string, values: array<string, mixed>, fieldTypes: array<string, string>, children: list<array<string, mixed>>}>>, version: string}
```

## Nvl\Content\Services\ContentSchemaCompiler

```text
final readonly class Nvl\Content\Services\ContentSchemaCompiler
public function __construct(Nvl\Content\Services\ContentFieldPresetRegistry $presets): mixed
public function compile(array $schema): Nvl\Content\Schema\ContentSchema
@param array<array-key, mixed> $schema
public function compilePreset(Nvl\Content\Contracts\ContentFieldPreset $preset): Nvl\Content\Schema\ContentFieldDefinition
```

## Nvl\Content\Services\ContentScopeRegistry

```text
final class Nvl\Content\Services\ContentScopeRegistry
public function assert(string $scope, string $scopeKey, array $definition): void
@param array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>} $definition
resolved @param array{key: string, name: string, description: (string | null), category: string, version: int, view: (string | null), schema: Nvl\Content\Schema\ContentSchema, defaults: array<string, mixed>, allowedScopes: list<string>, allowedRegions: list<string>, isActive: bool, sortOrder: int, jsonSchema: array<string, mixed>} $definition
public function assertRegistered(array $aliases): void
@param list<string> $aliases
```

## Nvl\Content\Services\ContentSnapshotIntegrity

```text
final readonly class Nvl\Content\Services\ContentSnapshotIntegrity implements Nvl\Content\Contracts\ContentSnapshotIntegrity
public function __construct(Nvl\Content\Services\CanonicalJson $json): mixed
public function assertValid(array $snapshot): void
@param array<string, mixed> $snapshot
public function digest(array $snapshot): string
@param array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)} $snapshot
public function rebind(array $source, string $type, string $id, array $context, ?array $blocks=null): array
@param array<string, mixed> $context
@param (list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}> | null) $blocks
@param array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)} $source
@return array{ownerType: string, ownerId: string, group: string, blocks: list<array{placementId: string, parentId: (string | null), key: string, region: string, sortOrder: int, blockId: string, definitionKey: string, definitionSchema: array<string, mixed>, definitionView: (string | null), visibility: string, values: array<string, mixed>, translations: array<string, array<string, mixed>>, overrides: array<string, mixed>, blockRevision: int, placementRevision: int}>, version: string, tenantId: (string | null), formatVersion: int, ownershipContext: (array<string, mixed> | null)}
```

## Nvl\Content\Traits\HasContent

```text
trait Nvl\Content\Traits\HasContent
@mixin Model
resolved @mixin Illuminate\Database\Eloquent\Model
public function contentGroups(): array
@return list<string>
public function delete(): ?bool
```

## Nvl\Content\Validation\ContentValidationContext

```text
final readonly class Nvl\Content\Validation\ContentValidationContext
public function __construct(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, string $locale, string $path, Nvl\Content\Enums\ContentVisibility $visibility, bool $publishing=false, ?Nvl\Content\Services\ContentRenderResources $resources=null, ?Illuminate\Database\Eloquent\Model $owner=null, bool $publicOnly=false, ?string $group=null, bool $localized=false, bool $resolveExternal=true): mixed
public function nested(string $segment): self
public readonly Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor
public readonly ?string $group
public readonly string $locale
public readonly bool $localized
public readonly ?Illuminate\Database\Eloquent\Model $owner
public readonly string $path
public readonly bool $publicOnly
public readonly bool $publishing
public readonly bool $resolveExternal
public readonly ?Nvl\Content\Services\ContentRenderResources $resources
public readonly Nvl\Content\Enums\ContentVisibility $visibility
```
