<?php

declare(strict_types=1);

namespace Nvl\Content\Exceptions;

use Nvl\Content\Enums\ContentResponseCode;
use Throwable;

/** Signals an invalid explicitly selected compiled-definition cache. @api */
final class ContentDefinitionCacheException extends ContentException
{
    /** Create a diagnostic failure without exposing cache paths or source content. */
    public static function invalid(string $reason, ?Throwable $previous = null): self
    {
        return new self(
            message: 'Content definition cache is invalid: '.$reason.' Run nvl:content:cache with the configured deployment version.',
            responseCode: ContentResponseCode::DefinitionCacheInvalid,
            suggestedStatus: 500,
            previous: $previous,
        );
    }
}
