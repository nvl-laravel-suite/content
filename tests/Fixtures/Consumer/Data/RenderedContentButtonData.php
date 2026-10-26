<?php

declare(strict_types=1);

namespace Nvl\Content\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Enums\ContentButtonEmphasis;
use Nvl\Content\Enums\ContentLinkRelationship;
use Nvl\Content\Enums\ContentLinkTarget;
use Nvl\Content\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Safe semantic projection of one linked call-to-action.
 */
final class RenderedContentButtonData extends Data
{
    use DataTransform;

    /**
     * @param  list<ContentLinkRelationship>  $rel
     */
    public function __construct(public readonly string $label, public readonly string $href, public readonly ?string $title, public readonly ContentLinkTarget $target, public readonly array $rel, public readonly ContentButtonEmphasis $emphasis) {}
}
