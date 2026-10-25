<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

/**
 * Direction-aware semantic alignment for adaptable rich content sections.
 *
 * @api
 */
enum ContentAlignment: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';
}
