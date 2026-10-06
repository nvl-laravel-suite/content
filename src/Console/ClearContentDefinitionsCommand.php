<?php

declare(strict_types=1);

namespace Nvl\Content\Console;

use Illuminate\Console\Command;
use Nvl\Content\Services\CompiledContentDefinitionCache;

/** Explicitly removes the Content cache without changing the host's cache policy. */
final class ClearContentDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'nvl:content:clear';

    /** @var string */
    protected $description = 'Remove the compiled Content definition cache';

    /** Clear the cache without source discovery or compilation. */
    public function handle(CompiledContentDefinitionCache $cache): int
    {
        $cache->clear();
        $this->info('Content definition cache cleared. Required-cache hosts must regenerate it before normal startup.');

        return self::SUCCESS;
    }
}
