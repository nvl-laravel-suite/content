<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

/**
 * Safe browsing-context targets supported by semantic links and buttons.
 *
 * @api
 */
enum ContentLinkTarget: string
{
    case SameContext = '_self';
    case NewContext = '_blank';
}
