<?php

declare(strict_types=1);

namespace Nvl\Content\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Support\Enums\SystemActor;

/**
 * Reports or applies source definition synchronization.
 */
final class SyncContentDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'nvl:content:definitions:sync
        {--dry-run : Report the deterministic plan without writing the mirror}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Synchronize source-controlled content definitions into the database mirror';

    /** Native Content operation. */
    public function handle(ContentDefinitions $content): int
    {
        $format = $this->option('format');
        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            throw new InvalidArgumentException('The content definition sync format must be text or json.');
        }
        $plan = app(ContentDefinitions::class)->sync(SystemActor::Background, (bool) $this->option('dry-run'));
        if ($format === 'json') {
            $this->line((string) json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach (['create', 'update', 'unchanged', 'orphan'] as $operation) {
                foreach ($plan[$operation] as $key) {
                    $this->line("{$operation}: {$key}");
                }
            }
        }

        return self::SUCCESS;
    }
}
