<?php

declare(strict_types=1);

namespace Nvl\Content\FieldTypes;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nvl\Content\Enums\ContentVisibility;
use Nvl\Content\Schema\ContentFieldDefinition;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Content\Validation\ContentValidationContext;
use Nvl\Media\Contracts\MediaAuthorization;
use Nvl\Media\Enums\MediaAbility;
use Nvl\Media\Enums\MediaVisibility;
use Nvl\Media\Models\Media;
use Nvl\Media\Services\MediaDeliveryPolicy;
use Nvl\Media\Services\MediaModelRegistry;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/**
 * Validates media IDs and projects binaries through Media's safe public contracts.
 */
final class MediaFieldTypeAdapter extends AbstractFieldTypeAdapter
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(private readonly bool $multiple) {}

    /** Return the stable public field or resolver alias. */
    public function alias(): string
    {
        return $this->multiple ? 'media_collection' : 'media';
    }

    /**
     * @return string|list<string>|null
     */
    public function normalize(mixed $value, ContentFieldDefinition $field, ContentValidationContext $context): string|array|null
    {
        $this->assertEnabled();
        if ($value === null) {
            return null;
        }
        $ids = $this->multiple ? $value : [$value];
        if (! is_array($ids) || ! array_is_list($ids)) {
            throw new InvalidArgumentException("Content field [{$context->path}] must contain media IDs.");
        }
        $maximum = $field->setting('max_items', ContentConfiguration::positiveInteger('nvl-content.media.maximum_per_field', 50));
        if (! is_int($maximum) || count($ids) > $maximum) {
            throw new InvalidArgumentException("Content field [{$context->path}] contains too many media items.");
        }
        $normalized = [];
        foreach ($ids as $id) {
            if (! is_string($id) || ! Str::isUuid($id)) {
                throw new InvalidArgumentException("Content field [{$context->path}] contains an invalid media ID.");
            }
            if (! $context->resolveExternal) {
                $normalized[] = $id;

                continue;
            }
            $media = MediaModelRegistry::query()->find($id);
            if (! $media instanceof Media || ! $media->isAvailable()) {
                throw new InvalidArgumentException("Media [{$id}] for [{$context->path}] is unavailable.");
            }
            $this->assertVisibility($media, $context);
            $this->assertMimeType($media, $field, $context);
            if ($context->actor instanceof PublicActor) {
                throw new InvalidArgumentException('Anonymous callers cannot attach Media.');
            }
            $this->assertAuthorized($media, $context->actor, $context);
            $normalized[] = $media->id;
        }
        $normalized = array_values(array_unique($normalized));

        return $this->multiple ? $normalized : $normalized[0] ?? null;
    }

    /** Resolve admitted Media references into the bounded field projection. */
    public function render(mixed $value, ContentFieldDefinition $field, ContentValidationContext $context): mixed
    {
        $this->assertEnabled();
        if ($value === null) {
            return null;
        }
        $ids = $this->multiple ? $value : [$value];
        if (! is_array($ids)) {
            return null;
        }
        $identifiers = [];
        foreach ($ids as $id) {
            if (is_string($id) && Str::isUuid($id)) {
                $identifiers[] = $id;
            }
        }
        $media = $context->resources?->media($identifiers) ?? MediaModelRegistry::query()->with(['translations', 'imageVariations', 'associations'])->whereIn('id', $identifiers)->get()->keyBy('id');
        $rendered = [];
        foreach ($ids as $id) {
            if (! is_string($id) || ! Str::isUuid($id)) {
                continue;
            }
            $item = $media->get($id);
            if (! $item instanceof Media || ! $item->isAvailable()) {
                continue;
            }
            if ($item->visibility === MediaVisibility::Public) {
                $public = ['id' => $item->id, 'type' => $item->type->value, 'mimeType' => $item->mime_type, 'url' => $item->buildPublicUrl()];
                if ($item->is_public && $public['url'] !== '') {
                    $rendered[] = $public;
                }

                continue;
            }
            if ($context->actor instanceof PublicActor || ! app(MediaAuthorization::class)->allows($context->actor, MediaAbility::Download, $item, $context->owner)) {
                continue;
            }
            $minutes = ContentConfiguration::positiveInteger('nvl-content.media.private_url_ttl_minutes', 15);
            $maximum = app(MediaDeliveryPolicy::class)->maximumTtl($item->disk);
            $seconds = $minutes > intdiv($maximum, 60) ? $maximum : $minutes * 60;
            $rendered[] = ['id' => $item->id, 'type' => $item->type->value, 'mimeType' => $item->mime_type, 'url' => $item->buildPrivateUrl(actor: $context->actor, expiration: (new DateTimeImmutable)->add(new DateInterval("PT{$seconds}S")))];
        }

        return $this->multiple ? $rendered : $rendered[0] ?? null;
    }

    /** Native Content operation. */
    private function assertVisibility(Media $media, ContentValidationContext $context): void
    {
        if ($context->visibility === ContentVisibility::Public && (bool) config('nvl-content.media.require_public_for_public_blocks', true) && $media->visibility !== MediaVisibility::Public) {
            throw new InvalidArgumentException("Public content field [{$context->path}] may only reference public media.");
        }
        if ($context->visibility === ContentVisibility::Private && $media->visibility === MediaVisibility::Private && ! (bool) config('nvl-content.media.allow_private_for_private_blocks', true)) {
            throw new InvalidArgumentException("Private media is disabled for content field [{$context->path}].");
        }
    }

    /** Native Content operation. */
    private function assertMimeType(Media $media, ContentFieldDefinition $field, ContentValidationContext $context): void
    {
        $allowed = $field->setting('mime_types', []);
        if (! is_array($allowed)) {
            throw new InvalidArgumentException("Content field [{$context->path}] mime_types must be an array.");
        }
        $patterns = [];
        foreach ($allowed as $mimeType) {
            if (! is_string($mimeType)) {
                throw new InvalidArgumentException("Content field [{$context->path}] mime_types must contain strings.");
            }
            $patterns[] = $mimeType;
        }
        if ($patterns !== [] && ! Str::is($patterns, $media->mime_type)) {
            throw new InvalidArgumentException("Media [{$media->id}] has an unsupported MIME type for [{$context->path}].");
        }
    }

    /** Native Content operation. */
    private function assertAuthorized(Media $media, Authenticatable|SystemActor $actor, ContentValidationContext $context): void
    {
        $ability = $media->visibility === MediaVisibility::Public ? MediaAbility::Reuse : MediaAbility::Associate;
        if (! app(MediaAuthorization::class)->allows($actor, $ability, $media, $context->owner)) {
            throw new InvalidArgumentException("Media [{$media->id}] is not reusable by the actor for [{$context->path}].");
        }
    }

    private function assertEnabled(): void
    {
        if (! config('nvl-content.media.enabled', false) || ! class_exists(Media::class) || ! app()->bound(MediaAuthorization::class)) {
            throw new InvalidArgumentException('Content Media fields require the enabled and registered nvl/media adapter.');
        }
    }
}
