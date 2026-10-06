<?php

declare(strict_types=1);

namespace Nvl\Content\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Nvl\Content\Services\CompiledContentDefinitionCache;

/** Explicitly compiles and publishes the selected host's Content definitions. */
final class CacheContentDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'nvl:content:cache {--cache-version= : Deployment version matching configured NVL_CONTENT_DEFINITIONS_VERSION}';

    /** @var string */
    protected $description = 'Atomically compile Content definitions into a bounded JSON cache';

    /** Compile source only after all host extensions have been registered. */
    public function handle(CompiledContentDefinitionCache $cache): int
    {
        $version = $this->input->getOption('cache-version');
        if ($version !== null && ! is_string($version)) {
            throw new InvalidArgumentException('The Content cache version must be a string.');
        }
        $cache->write($version);
        $this->info('Content definitions cached. Restart long-lived workers after changing the deployment version.');

        return self::SUCCESS;
    }
}
