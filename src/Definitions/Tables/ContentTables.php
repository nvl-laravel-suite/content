<?php

declare(strict_types=1);

namespace Nvl\Content\Definitions\Tables;

use Nvl\Support\Config\PackageStorage;

/**
 * Defines the canonical table names owned by the Content package.
 */
final class ContentTables
{
    public const string Definitions = 'nvl_content_definitions';

    public const string Blocks = 'nvl_content_blocks';

    public const string BlocksI18n = 'nvl_content_blocks_i18n';

    public const string Placements = 'nvl_content_placements';

    public const string Revisions = 'nvl_content_revisions';

    /** Return one configured logical or historical package table. */
    public static function get(string $key): string
    {
        return PackageStorage::resolveTable('content', $key);
    }

    private function __construct() {}
}
