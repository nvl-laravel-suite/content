<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Explicit sanitized HTML projection; consumers choose how and where to render it.
 */
final class RenderedRichTextData extends Data
{
    use DataTransform;

    public function __construct(public readonly string $html) {}
}
