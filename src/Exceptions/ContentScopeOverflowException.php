<?php

declare(strict_types=1);

namespace Nvl\Content\Exceptions;

/**
 * @api

 * Raised when a complete scope read would exceed its deterministic bound.
 */
final class ContentScopeOverflowException extends ContentException {}
