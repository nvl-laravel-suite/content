<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * Safe semantic projection of an image asset and its content-owned presentation metadata.
 */
final class RenderedContentImageData extends Data
{
    use DataTransform;

    /** @param array<string,mixed>|RenderedPrivateMediaData|null $media */
    public function __construct(
        #[LiteralTypeScriptType('Nvl.Media.Data.Display.PublicMedia | Nvl.Content.Data.RenderedPrivateMediaData | null')]
        /** @var array<string,mixed>|RenderedPrivateMediaData|null */
        public readonly array|RenderedPrivateMediaData|null $media,
        public readonly ?string $alt,
        public readonly ?string $title,
        public readonly RenderedRichTextData|string|null $caption,
        public readonly ?string $credit,
        public readonly bool $decorative,
        public readonly ?float $focalX,
        public readonly ?float $focalY
    ) {}
}
