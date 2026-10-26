<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Enums\ContentHeadingLevel;
use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Semantic heading projection with localized copy and a structural heading level.
 */
final class RenderedContentHeadingData extends Data
{
    use DataTransform;

    public function __construct(public readonly ?string $eyebrow, public readonly string $title, public readonly RenderedRichTextData|string|null $description, public readonly ContentHeadingLevel $level) {}
}
