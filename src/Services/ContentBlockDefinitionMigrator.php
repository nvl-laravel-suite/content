<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Nvl\Content\Enums\ContentRevisionEvent;
use Nvl\Content\Enums\ContentStatus;
use Nvl\Content\Events\ContentBlockChanged;
use Nvl\Content\Exceptions\ContentDefinitionMigrationException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Support\ContentArrays;
use Nvl\Content\Validation\ContentValueValidator;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Translatable\Services\RelatedTranslationStore;
use Throwable;

/**
 * Applies, validates, and persists one complete definition migration chain.
 *
 * The caller owns the surrounding transaction and row lock.
 *
 * @internal
 */
final readonly class ContentBlockDefinitionMigrator
{
    /** Native Content operation. */
    public function __construct(private ContentDefinitionMigrationRegistry $migrations, private ContentDefinitionRegistry $definitions, private ContentValueValidator $validator, private ContentPayloadGuard $guard, private ContentMediaSynchronizer $media, private ContentRevisionRecorder $revisions, private CanonicalJson $json, private RelatedTranslationStore $translationWriter, private ContentBlockPlacementSynchronizer $placements, private ContentScopeRegistry $scopes, private DomainEventDispatcher $domainEvents) {}

    /** Native Content operation. */
    public function migrate(ContentBlock $block, int $targetVersion, Authenticatable|SystemActor $actor): ContentBlock
    {
        $block->loadMissing(['definition', 'translations']);
        $definition = $this->definitions->get($block->definition->key);
        if ($targetVersion !== $definition['version']) {
            throw new InvalidArgumentException("Content definition migration target [{$targetVersion}] is no longer current "."for [{$definition['key']}:{$definition['version']}].");
        }
        if ($block->definition_version >= $targetVersion) {
            throw new InvalidArgumentException("Content block [{$block->id}] is not older than definition "."[{$definition['key']}:{$targetVersion}].");
        }
        $definitionHash = $this->json->hash($definition);
        if (! hash_equals($block->definition->source_hash, $definitionHash)) {
            throw new InvalidArgumentException("Content definition [{$definition['key']}] is stale; synchronize it before migration.");
        }
        $this->scopes->assert($block->scope, $block->scope_key, $definition);
        $values = ContentArrays::stringMap(is_array($block->values) ? $block->values : [], "content block {$block->id} values");
        $translations = $this->translations($block);
        $metadata = ContentArrays::stringMap(is_array($block->metadata) ? $block->metadata : [], "content block {$block->id} metadata");
        foreach ($this->migrations->path($definition['key'], $block->definition_version, $targetVersion) as $migration) {
            try {
                $migrated = $migration->migrate(['blockId' => $block->id, 'blockKey' => $block->key, 'scope' => $block->scope, 'scopeKey' => $block->scope_key, 'status' => $block->status, 'visibility' => $block->visibility, 'fromVersion' => $migration->fromVersion(), 'toVersion' => $migration->toVersion(), 'values' => $values, 'translations' => $translations, 'metadata' => $metadata]);
            } catch (Throwable $exception) {
                throw ContentDefinitionMigrationException::forStep(blockId: $block->id, definition: $definition['key'], migration: $migration, previous: $exception);
            }
            $values = ContentArrays::stringMap($migrated['values'], "content block {$block->id} migrated values");
            $translations = ContentArrays::translations($migrated['translations'], "content block {$block->id} migrated translations");
            $metadata = ContentArrays::stringMap($migrated['metadata'], "content block {$block->id} migrated metadata");
        }
        $schema = $definition['schema'];
        $validated = $this->validator->validate(schema: $schema, values: $values, translations: $translations, actor: $actor, visibility: $block->visibility, publishing: $block->status === ContentStatus::Published);
        $this->guard->metadata($metadata);
        $block->forceFill(['values' => $validated->values, 'metadata' => $metadata === [] ? null : $metadata, 'definition_version' => $definition['version'], 'definition_hash' => $definitionHash, 'definition_schema' => $schema, 'definition_view' => $definition['view'], 'revision' => $block->revision + 1, 'updated_by_type' => $actor instanceof SystemActor ? 'system' : ($actor instanceof Model ? $actor->getMorphClass() : $actor::class), 'updated_by_id' => $actor instanceof SystemActor ? null : $actor->getAuthIdentifier()])->save();
        $translationRows = $this->translationPayloads($validated->translations);
        $translationDefinition = $block->translationDefinition();
        foreach ($translationRows as $locale => $attributes) {
            $this->translationWriter->upsert($block, $translationDefinition, $locale, $attributes);
        }
        $this->translationWriter->deleteExcept($block, $translationDefinition, array_keys($translationRows));
        $block->load('translations');
        $this->placements->synchronize($block, $actor);
        if (! $block->trashed()) {
            $this->media->synchronize($block, $schema, $validated->values, $validated->translations, $actor);
        }
        $this->revisions->record($block, ContentRevisionEvent::Migrated, $actor);
        $this->domainEvents->dispatch(new ContentBlockChanged($block->id, ContentRevisionEvent::Migrated, $block->revision, $actor), $block->getConnection());

        return $block->refresh()->load(['definition', 'translations']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function translations(ContentBlock $block): array
    {
        $translations = [];
        foreach ($block->translations as $translation) {
            $locale = $translation->getAttribute('locale');
            $values = $translation->getAttribute('values');
            if (is_string($locale) && is_array($values)) {
                $translations[$locale] = ContentArrays::stringMap($values, "content translation {$locale}");
            }
        }
        ksort($translations);

        return $translations;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, array{values: array<string, mixed>}>
     */
    private function translationPayloads(array $translations): array
    {
        $payloads = [];
        foreach ($translations as $locale => $values) {
            $payloads[$locale] = ['values' => $values];
        }

        return $payloads;
    }
}
