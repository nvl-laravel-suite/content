<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

/**
 * Selects non-destructive deep patching or complete replacement semantics.
 *
 * @api
 */
enum ContentMutationMode: string
{
    case Replace = 'replace';
    case Patch = 'patch';
}
