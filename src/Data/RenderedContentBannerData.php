<?php

declare(strict_types=1);

namespace Nvl\Content\Data;

use Nvl\Content\Enums\ContentAlignment;
use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Semantic banner projection composed from reusable heading, image, and action presets.
 */
final class RenderedContentBannerData extends Data
{
    use DataTransform;

    public function __construct(
        public readonly RenderedContentHeadingData $heading,
        public readonly ?RenderedContentImageData $image,
        public readonly ?RenderedContentButtonData $primaryAction,
        public readonly ?RenderedContentButtonData $secondaryAction,
        public readonly ContentAlignment $alignment,
    ) {}
}
