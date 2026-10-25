<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

/**
 * Delivery policy classification applied to blocks and referenced Media.
 *
 * @api
 */
enum ContentVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
