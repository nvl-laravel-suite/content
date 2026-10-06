<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Content\Support\ContentConfiguration;
use Nvl\Support\Config\PackageStorage;

return new class extends Migration
{
    /** Use the effective package connection for Laravel's migration transaction. */
    public function getConnection(): ?string
    {
        return PackageStorage::connection('content');
    }

    public function up(): void
    {
        $schema = Schema::connection(ContentConfiguration::connection());
        foreach (['blocks', 'blocks_i18n', 'placements', 'revisions'] as $key) {
            $table = ContentConfiguration::table($key);
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id')->nullable(false)->change());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(ContentConfiguration::connection());
        foreach (['blocks', 'blocks_i18n', 'placements', 'revisions'] as $key) {
            $table = ContentConfiguration::table($key);
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id')->nullable()->change());
        }
    }
};
