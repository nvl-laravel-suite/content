<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentAuthorization;
use Nvl\Content\Enums\ContentAbility;
use Nvl\Content\Enums\ContentMutationMode;
use Nvl\Content\Enums\ContentRevisionEvent;
use Nvl\Content\Enums\ContentStatus;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Events\ContentBlockChanged;
use Nvl\Content\Exceptions\StaleContentException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentDefinition;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Validation\ContentObjectRule;
use Nvl\Content\Validation\ContentValueValidator;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;
use Nvl\Translatable\Services\RelatedTranslationStore;

/**
 * Native Content workflow family.
 *
 * @api
 */
final class ContentBlocks
{
    public function __construct(private ContentAuthorization $authorization, private ContentDefinitionRegistry $definitions, private ContentScopeRegistry $scopes, private ContentValueValidator $validator, private ContentMediaSynchronizer $media, private ContentRevisionRecorder $revisions, private CanonicalJson $json, private ContentPayloadGuard $guard, private ContentPatch $patch, private ContentIdentityGuard $identities, private RelatedTranslationStore $translations, private DomainEventDispatcher $domainEvents, private ContentDefinitionVersionGuard $definitionVersions, private RelatedTranslationStore $translationWriter, private ContentBlockPlacementSynchronizer $placements) {}

    /** Create a validated block and localized values on its canonical source.
     * @param  array{definition:string,key:string,scope?:string,scopeKey?:string,visibility?:ContentVisibility|string,values?:array<string, mixed>,translations?:array<string, array<string, mixed>>,metadata?:array<string, mixed>}  $data
     */
    public function create(array $data, Authenticatable|SystemActor $actor): ContentBlock
    {
        Validator::make($data, self::createRules())->validate();
        $data += ['scope' => 'global', 'scopeKey' => '*', 'visibility' => ContentVisibility::Public, 'values' => [], 'translations' => [], 'metadata' => []];
        if (is_string($data['visibility'])) {
            $data['visibility'] = ContentVisibility::from($data['visibility']);
        }
        $this->authorization->authorize(ContentAbility::Create, $actor, context: ['definition' => $data['definition'], 'key' => $data['key'], 'scope' => $data['scope'], 'scope_key' => $data['scopeKey'], 'visibility' => $data['visibility']->value, 'status' => ContentStatus::Draft->value]);
        $this->identities->blockKey($data['key']);
        $definition = $this->definitions->get($data['definition']);
        $this->scopes->assert($data['scope'], $data['scopeKey'], $definition);
        $inputValues = ContentArrays::stringMap($data['values'], 'content block values');
        $inputTranslations = ContentArrays::translations($data['translations'], 'content block translations');
        $metadata = ContentArrays::stringMap($data['metadata'], 'content block metadata');
        $modelDefinition = ContentDefinition::query()->where('key', $definition['key'])->where('is_active', true)->whereNull('orphaned_at')->first();
        if (! $modelDefinition instanceof ContentDefinition) {
            throw new InvalidArgumentException("Content definition [{$definition['key']}] is not synchronized and active.");
        }
        $definitionHash = $this->json->hash($definition);
        if (! hash_equals($modelDefinition->source_hash, $definitionHash)) {
            throw new InvalidArgumentException("Content definition [{$definition['key']}] is stale; synchronize definitions first.");
        }
        $values = $this->patch->merge($definition['defaults'], $inputValues);
        $schema = $definition['schema'];
        $validated = $this->validator->validate($schema, $values, $inputTranslations, $actor, $data['visibility']);
        $this->guard->metadata($metadata);

        return DB::connection($modelDefinition->getConnectionName())->transaction(function () use ($actor, $data, $definition, $definitionHash, $metadata, $modelDefinition, $schema, $validated): ContentBlock {
            $lockedDefinition = ContentDefinition::query()->whereKey($modelDefinition->id)->where('is_active', true)->whereNull('orphaned_at')->lockForUpdate()->first();
            if (! $lockedDefinition instanceof ContentDefinition) {
                throw new InvalidArgumentException("Content definition [{$definition['key']}] is not synchronized and active.");
            }
            if (! hash_equals($lockedDefinition->source_hash, $definitionHash)) {
                throw new InvalidArgumentException("Content definition [{$definition['key']}] is stale; synchronize definitions first.");
            }
            $block = ContentModelRegistry::create(['definition_id' => $lockedDefinition->id, 'key' => $data['key'], 'scope' => $data['scope'], 'scope_key' => $data['scopeKey'], 'visibility' => $data['visibility'], 'values' => $validated->values, 'metadata' => $metadata === [] ? null : $metadata, 'definition_version' => $definition['version'], 'definition_hash' => $definitionHash, 'definition_schema' => $schema, 'definition_view' => $definition['view'], 'created_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'created_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier(), 'updated_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'updated_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier()]);
            $translationRows = $this->createContentBlockTranslationPayloads($validated->translations);
            $translationDefinition = $block->translationDefinition();
            foreach ($translationRows as $locale => $attributes) {
                $this->translations->upsert($block, $translationDefinition, $locale, $attributes);
            }
            $this->translations->deleteExcept($block, $translationDefinition, array_keys($translationRows));
            $this->media->synchronize($block, $schema, $validated->values, $validated->translations, $actor);
            $this->revisions->record($block, ContentRevisionEvent::Created, $actor);
            $this->domainEvents->dispatch(new ContentBlockChanged($block->id, ContentRevisionEvent::Created, $block->revision, $actor), $block->getConnection());

            return $block->load(['definition', 'translations']);
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, array{values: array<string, mixed>}>
     */
    private function createContentBlockTranslationPayloads(array $translations): array
    {
        $payloads = [];
        foreach ($translations as $locale => $values) {
            $payloads[$locale] = ['values' => $values];
        }

        return $payloads;
    }

    /** Apply an authorized revision-checked block mutation.
     * @param  array{expectedRevision:int,mode?:ContentMutationMode|string,visibility?:ContentVisibility|string|null,values?:array<string, mixed>,translations?:array<string, array<string, mixed>>,metadata?:array<string, mixed>}  $data
     */
    public function update(ContentBlock|string $block, array $data, Authenticatable|SystemActor $actor): ContentBlock
    {
        Validator::make($data, self::updateRules())->validate();
        $data += ['mode' => ContentMutationMode::Patch, 'visibility' => null, 'values' => [], 'translations' => [], 'metadata' => []];
        if (is_string($data['mode'])) {
            $data['mode'] = ContentMutationMode::from($data['mode']);
        }
        if (is_string($data['visibility'])) {
            $data['visibility'] = ContentVisibility::from($data['visibility']);
        }
        $blockId = $block instanceof ContentBlock ? $block->id : $block;
        $connection = ContentModelRegistry::newInstance()->getConnectionName();
        $inputValues = ContentArrays::stringMap($data['values'], 'content block values');
        $inputTranslations = ContentArrays::translations($data['translations'], 'content block translations');
        $inputMetadata = ContentArrays::stringMap($data['metadata'], 'content block metadata');

        return DB::connection($connection)->transaction(function () use ($actor, $blockId, $data, $inputMetadata, $inputTranslations, $inputValues): ContentBlock {
            $model = ContentModelRegistry::query()->with(['definition', 'translations'])->lockForUpdate()->findOrFail($blockId);
            $visibility = $data['visibility'] ?? $model->visibility;
            $authorizationContext = ['definition' => $model->definition->key, 'key' => $model->key, 'scope' => $model->scope, 'scope_key' => $model->scope_key, 'status' => $model->status->value, 'current_visibility' => $model->visibility->value, 'target_visibility' => $visibility->value, 'mutation_mode' => $data['mode']->value, 'published' => $model->status === ContentStatus::Published];
            $this->authorization->authorize(ContentAbility::Update, $actor, $model, context: $authorizationContext);
            if ($model->status === ContentStatus::Published && $model->visibility === ContentVisibility::Private && $visibility === ContentVisibility::Public) {
                $this->authorization->authorize(ContentAbility::Publish, $actor, $model, context: [...$authorizationContext, 'visibility_transition' => true]);
            }
            if ($model->revision !== $data['expectedRevision']) {
                throw StaleContentException::forRevision($model->id, $data['expectedRevision'], $model->revision);
            }
            $definition = $this->definitions->get($model->definition->key);
            $definitionHash = $this->json->hash($definition);
            if (! hash_equals($model->definition->source_hash, $definitionHash)) {
                throw new InvalidArgumentException("Content definition [{$definition['key']}] is stale; synchronize it before editing.");
            }
            $this->definitionVersions->assertCurrent($model, $definition);
            $schema = $definition['schema'];
            $currentTranslations = $this->updateContentBlockTranslations($model);
            $values = $data['mode'] === ContentMutationMode::Patch ? $this->patch->merge(is_array($model->values) ? $model->values : [], $inputValues) : $inputValues;
            $translations = $data['mode'] === ContentMutationMode::Patch ? $this->updateContentBlockPatchTranslations($currentTranslations, $inputTranslations) : $inputTranslations;
            $metadata = $data['mode'] === ContentMutationMode::Patch ? $this->patch->merge(is_array($model->metadata) ? $model->metadata : [], $inputMetadata) : $inputMetadata;
            $validated = $this->validator->validate($schema, $values, $translations, $actor, $visibility, publishing: $model->status === ContentStatus::Published);
            $this->guard->metadata($metadata);
            $model->forceFill(['visibility' => $visibility, 'values' => $validated->values, 'metadata' => $metadata === [] ? null : $metadata, 'definition_version' => $definition['version'], 'definition_hash' => $definitionHash, 'definition_schema' => $schema, 'definition_view' => $definition['view'], 'revision' => $model->revision + 1, 'updated_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'updated_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier()])->save();
            $translationRows = $this->updateContentBlockTranslationPayloads($validated->translations);
            $translationDefinition = $model->translationDefinition();
            foreach ($translationRows as $locale => $attributes) {
                $this->translationWriter->upsert($model, $translationDefinition, $locale, $attributes);
            }
            $this->translationWriter->deleteExcept($model, $translationDefinition, array_keys($translationRows));
            $model->load('translations');
            $this->placements->synchronize($model, $actor);
            $this->media->synchronize($model, $schema, $validated->values, $validated->translations, $actor);
            $this->revisions->record($model, ContentRevisionEvent::Updated, $actor);
            $this->domainEvents->dispatch(new ContentBlockChanged($model->id, ContentRevisionEvent::Updated, $model->revision, $actor), $model->getConnection());

            return $model->refresh()->load(['definition', 'translations']);
        }, 3);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function updateContentBlockTranslations(ContentBlock $block): array
    {
        $translations = [];
        foreach ($block->translations as $translation) {
            $locale = $translation->getAttribute('locale');
            $values = $translation->getAttribute('values');
            if (is_string($locale) && is_array($values)) {
                $translations[$locale] = ContentArrays::stringMap($values, "content translation {$locale}");
            }
        }

        return $translations;
    }

    /**
     * @param  array<string, array<string, mixed>>  $current
     * @param  array<string, array<string, mixed>>  $patches
     * @return array<string, array<string, mixed>>
     */
    private function updateContentBlockPatchTranslations(array $current, array $patches): array
    {
        foreach ($patches as $locale => $patch) {
            $current[$locale] = $this->patch->merge($current[$locale] ?? [], $patch);
        }

        return $current;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, array{values: array<string, mixed>}>
     */
    private function updateContentBlockTranslationPayloads(array $translations): array
    {
        $payloads = [];
        foreach ($translations as $locale => $values) {
            $payloads[$locale] = ['values' => $values];
        }

        return $payloads;
    }

    /** Publish an admitted block at its expected revision. */
    public function publish(ContentBlock|string $block, int $expectedRevision, Authenticatable|SystemActor $actor): ContentBlock
    {
        $blockId = $block instanceof ContentBlock ? $block->id : $block;

        return DB::connection(ContentModelRegistry::newInstance()->getConnectionName())->transaction(function () use ($actor, $blockId, $expectedRevision): ContentBlock {
            $model = ContentModelRegistry::query()->with(['definition', 'translations'])->lockForUpdate()->findOrFail($blockId);
            $this->authorization->authorize(ContentAbility::Publish, $actor, $model);
            if ($model->revision !== $expectedRevision) {
                throw StaleContentException::forRevision($model->id, $expectedRevision, $model->revision);
            }
            $definition = $this->definitions->get($model->definition->key);
            $definitionHash = $this->json->hash($definition);
            if (! hash_equals($model->definition->source_hash, $definitionHash)) {
                throw new InvalidArgumentException("Content definition [{$definition['key']}] is stale; synchronize it before publishing.");
            }
            $this->definitionVersions->assertCurrent($model, $definition);
            $schema = $definition['schema'];
            $translations = [];
            foreach ($model->translations as $translation) {
                $locale = $translation->getAttribute('locale');
                $values = $translation->getAttribute('values');
                if (is_string($locale) && is_array($values)) {
                    $translations[$locale] = ContentArrays::stringMap($values, "content translation {$locale}");
                }
            }
            $validated = $this->validator->validate($schema, is_array($model->values) ? $model->values : [], $translations, $actor, $model->visibility, publishing: true);
            $model->forceFill(['status' => ContentStatus::Published, 'values' => $validated->values, 'definition_version' => $definition['version'], 'definition_hash' => $definitionHash, 'definition_schema' => $schema, 'definition_view' => $definition['view'], 'revision' => $model->revision + 1, 'published_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'published_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier(), 'published_at' => now(), 'updated_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'updated_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier()])->save();
            $this->placements->synchronize($model, $actor);
            $this->media->synchronize($model, $schema, $validated->values, $validated->translations, $actor);
            $this->revisions->record($model, ContentRevisionEvent::Published, $actor);
            $this->domainEvents->dispatch(new ContentBlockChanged($model->id, ContentRevisionEvent::Published, $model->revision, $actor), $model->getConnection());

            return $model->refresh()->load(['definition', 'translations']);
        }, 3);
    }

    /** Archive an admitted block at its expected revision. */
    public function archive(ContentBlock|string $block, int $expectedRevision, Authenticatable|SystemActor $actor): ContentBlock
    {
        $blockId = $block instanceof ContentBlock ? $block->id : $block;

        return DB::connection(ContentModelRegistry::newInstance()->getConnectionName())->transaction(function () use ($actor, $blockId, $expectedRevision): ContentBlock {
            $model = ContentModelRegistry::query()->lockForUpdate()->findOrFail($blockId);
            $this->authorization->authorize(ContentAbility::Archive, $actor, $model);
            if ($model->revision !== $expectedRevision) {
                throw StaleContentException::forRevision($model->id, $expectedRevision, $model->revision);
            }
            $model->forceFill(['status' => ContentStatus::Archived, 'revision' => $model->revision + 1, 'updated_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'updated_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier()])->save();
            $this->revisions->record($model, ContentRevisionEvent::Archived, $actor);
            $this->domainEvents->dispatch(new ContentBlockChanged($model->id, ContentRevisionEvent::Archived, $model->revision, $actor), $model->getConnection());

            return $model->refresh();
        });
    }

    /** Restore the exact configured block at its expected revision. */
    public function restore(ContentBlock|string $block, int $expectedRevision, Authenticatable|SystemActor $actor): ContentBlock
    {
        $blockId = $block instanceof ContentBlock ? $block->id : $block;

        return DB::connection(ContentModelRegistry::newInstance()->getConnectionName())->transaction(function () use ($actor, $blockId, $expectedRevision): ContentBlock {
            $model = ContentModelRegistry::query()->withTrashed()->with(['definition', 'translations'])->lockForUpdate()->findOrFail($blockId);
            $this->authorization->authorize(ContentAbility::Restore, $actor, $model);
            if (! $model->trashed()) {
                throw new InvalidArgumentException("Content block [{$model->id}] is not deleted.");
            }
            if ($model->revision !== $expectedRevision) {
                throw StaleContentException::forRevision($model->id, $expectedRevision, $model->revision);
            }
            $model->forceFill(['status' => ContentStatus::Draft, 'revision' => $model->revision + 1, 'published_by_type' => null, 'published_by_id' => null, 'published_at' => null, 'updated_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'updated_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier()])->restore();
            $translations = [];
            foreach ($model->translations as $translation) {
                $locale = $translation->getAttribute('locale');
                $values = $translation->getAttribute('values');
                if (is_string($locale) && is_array($values)) {
                    $translations[$locale] = ContentArrays::stringMap($values, "restored content translation {$locale}");
                }
            }
            $this->media->synchronize($model, $model->definition_schema, is_array($model->values) ? $model->values : [], $translations, $actor);
            $this->revisions->record($model, ContentRevisionEvent::Restored, $actor);
            $this->domainEvents->dispatch(new ContentBlockChanged($model->id, ContentRevisionEvent::Restored, $model->revision, $actor), $model->getConnection());

            return $model->refresh()->load(['definition', 'translations']);
        });
    }

    /** Delete an authorized unplaced block while retaining Media binaries. */
    public function delete(ContentBlock|string $block, int $expectedRevision, Authenticatable|SystemActor $actor): void
    {
        $blockId = $block instanceof ContentBlock ? $block->id : $block;
        DB::connection(ContentModelRegistry::newInstance()->getConnectionName())->transaction(function () use ($actor, $blockId, $expectedRevision): void {
            $model = ContentModelRegistry::query()->lockForUpdate()->findOrFail($blockId);
            $this->authorization->authorize(ContentAbility::Delete, $actor, $model);
            if ($model->revision !== $expectedRevision) {
                throw StaleContentException::forRevision($model->id, $expectedRevision, $model->revision);
            }
            if ($model->placements()->lockForUpdate()->first(['id']) !== null) {
                throw new InvalidArgumentException('A placed content block cannot be deleted; remove every placement first.');
            }
            $model->forceFill(['revision' => $model->revision + 1])->save();
            $this->revisions->record($model, ContentRevisionEvent::Deleted, $actor);
            $this->media->detachAll($model, $actor);
            $model->delete();
            $this->domainEvents->dispatch(new ContentBlockChanged($model->id, ContentRevisionEvent::Deleted, $model->revision, $actor), $model->getConnection());
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function createRules(): array
    {
        return ['definition' => ['required', 'string', 'max:191', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'key' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/'], 'scope' => ['sometimes', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'scopeKey' => ['sometimes', 'string', 'max:191'], 'visibility' => ['sometimes', Rule::enum(ContentVisibility::class)], 'values' => ['array', new ContentObjectRule], 'translations' => ['array', new ContentObjectRule, new SupportedLocaleMapRule(self::createContentLocales())], 'translations.*' => ['array', new ContentObjectRule], 'metadata' => ['array', new ContentObjectRule]];
    }

    /**
     * @return list<string>|null
     */
    private static function createContentLocales(): ?array
    {
        $configured = ContentConfiguration::stringList('nvl-content.locales.available');

        return $configured === [] ? null : $configured;
    }

    /**
     * @return array<string, mixed>
     */
    private static function updateRules(): array
    {
        return ['expectedRevision' => ['required', 'integer', 'min:1'], 'mode' => ['sometimes', Rule::enum(ContentMutationMode::class)], 'visibility' => ['nullable', Rule::enum(ContentVisibility::class)], 'values' => ['array', new ContentObjectRule], 'translations' => ['array', new ContentObjectRule, new SupportedLocaleMapRule(self::updateContentLocales())], 'translations.*' => ['array', new ContentObjectRule], 'metadata' => ['array', new ContentObjectRule]];
    }

    /**
     * @return list<string>|null
     */
    private static function updateContentLocales(): ?array
    {
        $configured = ContentConfiguration::stringList('nvl-content.locales.available');

        return $configured === [] ? null : $configured;
    }
}
