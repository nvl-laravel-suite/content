<?php

declare(strict_types=1);

namespace Nvl\Content\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Nvl\Content\Models\ContentBlock;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Schema\ContentSchema;
use Nvl\Media\Contracts\MediaAuthorization;
use Nvl\Media\Enums\MediaAbility;
use Nvl\Media\Enums\MediaVisibility;
use Nvl\Media\Models\Media;
use Nvl\Media\Models\MediaAssociation;
use Nvl\Media\Services\MediaAttachments;
use Nvl\Media\Services\MediaModelRegistry;
use Nvl\Support\Enums\SystemActor;

/**
 * Keeps Media associations aligned with normalized field IDs without owning binaries.
 *
 * @internal
 */
final readonly class ContentMediaSynchronizer
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private ContentMediaReferences $references, private DatabaseManager $database) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, array<string, mixed>>  $translations
     */
    public function synchronize(ContentBlock $block, ContentSchema $schema, array $values, array $translations, Authenticatable|SystemActor $actor): void
    {
        $desired = $this->references->extract($schema, $values, null);
        foreach ($translations as $locale => $localizedValues) {
            $desired = [...$desired, ...$this->references->extract($schema, $localizedValues, $locale)];
        }
        $this->synchronizeReferences($block, $desired, $actor, $block);
    }

    /**
     * Synchronize only Media explicitly referenced by one placement's overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function synchronizePlacement(ContentPlacement $placement, ContentSchema $schema, array $overrides, Authenticatable|SystemActor $actor, Model $owner): void
    {
        $this->synchronizeReferences($placement, $this->references->extract($schema, $overrides, null), $actor, $owner);
    }

    /**
     * Detach every Content-managed association while preserving Media records.
     */
    public function detachAll(ContentBlock|ContentPlacement $model, Authenticatable|SystemActor $actor): void
    {
        if (! $this->enabled()) {
            return;
        }
        $associations = MediaAssociation::query()->where('associable_type', $model->getMorphClass())->where('associable_id', $model->getKey())->where('collection', 'like', 'content:%')->orderBy('media_id')->get();
        if ($associations->isNotEmpty()) {
            $this->assertSharedConnection($model);
        }
        foreach ($associations as $association) {
            app(MediaAttachments::class)->detach($actor, $association->media_id, $model, $association->collection);
        }
    }

    /**
     * Reconcile one stable association target using Media's mutation services.
     *
     * @param  list<array{id: string, path: string, locale: string|null, order: int}>  $desired
     */
    private function synchronizeReferences(ContentBlock|ContentPlacement $model, array $desired, Authenticatable|SystemActor $actor, Model $authorizationOwner): void
    {
        if (! $this->enabled()) {
            if ($desired !== []) {
                throw new InvalidArgumentException('Content Media integration is disabled.');
            }

            return;
        }
        usort($desired, static fn (array $left, array $right): int => [$left['id'], $left['path'], $left['locale']] <=> [$right['id'], $right['path'], $right['locale']]);
        $current = MediaAssociation::query()->where('associable_type', $model->getMorphClass())->where('associable_id', $model->getKey())->where('collection', 'like', 'content:%')->orderBy('media_id')->get();
        if ($desired !== [] || $current->isNotEmpty()) {
            $this->assertSharedConnection($model);
        }
        $desiredKeys = [];
        foreach ($desired as $reference) {
            $collection = $this->collection($reference['path'], $reference['locale']);
            $key = $reference['id'].'|'.$collection;
            $desiredKeys[$key] = true;
            $media = MediaModelRegistry::query()->findOrFail($reference['id']);
            $ability = $media->visibility === MediaVisibility::Public ? MediaAbility::Reuse : MediaAbility::Associate;
            if (! app(MediaAuthorization::class)->allows($actor, $ability, $media, $authorizationOwner)) {
                throw new InvalidArgumentException("Media [{$media->id}] cannot be associated with content [{$model->id}].");
            }
            app(MediaAttachments::class)->attach(actor: $actor, media: $media, model: $model, collection: $collection, locale: $reference['locale'], order: $reference['order'], metadata: ['field_path' => $reference['path'], 'locale' => $reference['locale'], 'content_managed' => true], dispatchVariations: false, requirePublic: $media->visibility === MediaVisibility::Public);
        }
        foreach ($current as $association) {
            $key = $association->media_id.'|'.$association->collection;
            if (! isset($desiredKeys[$key])) {
                app(MediaAttachments::class)->detach($actor, $association->media_id, $model, $association->collection);
            }
        }
    }

    /** Native Content operation. */
    private function collection(string $path, ?string $locale): string
    {
        return 'content:'.substr(hash('sha256', ($locale ?? '*').'|'.$path), 0, 24);
    }

    /** Native Content operation. */
    private function assertSharedConnection(ContentBlock|ContentPlacement $model): void
    {
        $contentConnection = $this->database->connection($model->getConnectionName())->getName();
        $mediaConnection = $this->database->connection((new MediaAssociation)->getConnectionName())->getName();
        if ($contentConnection !== $mediaConnection) {
            throw new InvalidArgumentException('Content and Media must use the same named database connection so content and media-association writes remain atomic.');
        }
    }

    private function enabled(): bool
    {
        if (! config('nvl-content.media.enabled', false)) {
            return false;
        }
        if (! class_exists(MediaAttachments::class) || ! app()->bound(MediaAuthorization::class)) {
            throw new InvalidArgumentException('Content Media integration requires the installed and registered nvl/media adapter.');
        }

        return true;
    }
}
