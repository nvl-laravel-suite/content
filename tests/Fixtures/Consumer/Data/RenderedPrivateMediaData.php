<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Authorized short-lived projection of a private Media record.
 */
final class RenderedPrivateMediaData extends Data
{
    use DataTransform;

    public function __construct(public readonly string $id, public readonly string $type, public readonly string $mimeType, public readonly string $url) {}
}
