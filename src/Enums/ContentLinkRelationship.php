<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

/**
 * Allowlisted link relationship tokens supported by semantic navigation values.
 */
enum ContentLinkRelationship: string
{
    case NoFollow = 'nofollow';
    case NoOpener = 'noopener';
    case NoReferrer = 'noreferrer';
    case Sponsored = 'sponsored';
    case UserGenerated = 'ugc';
}
