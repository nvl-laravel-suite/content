<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

/**
 * Presentation-neutral semantic priority for linked calls to action.
 */
enum ContentButtonEmphasis: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Tertiary = 'tertiary';
}
