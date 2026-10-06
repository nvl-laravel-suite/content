<?php

declare(strict_types=1);

namespace Nvl\Content\Enums;

use Nvl\Support\Contracts\ResponseCode;

/**
 * @api
 * Stable machine-readable codes exposed by the optional Content HTTP API.
 */
enum ContentResponseCode: string implements ResponseCode
{
    case OperationFailed = 'operation_failed';
    case DefinitionCacheInvalid = 'definition_cache_invalid';

    case InvalidContent = 'invalid_content';
    case StaleContent = 'stale_content';
    case DefinitionMigrationRequired = 'definition_migration_required';
    case DefinitionMigrationFailed = 'definition_migration_failed';
}
