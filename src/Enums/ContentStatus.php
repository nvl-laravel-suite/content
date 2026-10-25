<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

/**
 * Publication lifecycle of a reusable content block.
 *
 * @api
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
