<?php

declare(strict_types=1);

namespace Nvl\Content\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Support\Enums\SystemActor;

/**
 * Plans or atomically applies one bounded definition migration batch.
 */
final class MigrateContentDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'nvl:content:definitions:migrate
        {--definition= : Restrict the batch to one definition key}
        {--limit= : Maximum blocks in this atomic batch}
        {--dry-run : Report the exact revision-safe plan without applying it}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Plan or migrate stored blocks to their current content definition versions';

    /** Native Content operation. */
    public function handle(ContentDefinitions $content): int
    {
        $format = $this->option('format');
        $definition = $this->option('definition');
        $limit = $this->option('limit');
        if (! in_array($format, ['text', 'json'], true)) {
            throw new InvalidArgumentException('The content definition migration format must be text or json.');
        }
        if ($definition !== null && $definition === '') {
            throw new InvalidArgumentException('The content definition migration key must be a non-empty string.');
        }
        if ($limit !== null && preg_match('/^[1-9][0-9]*$/', $limit) !== 1) {
            throw new InvalidArgumentException('The content definition migration limit must be a positive integer.');
        }
        $actor = SystemActor::Background;
        $plan = app(ContentDefinitions::class)->planMigration(actor: $actor, definition: $definition, limit: $limit !== null ? (int) $limit : null);
        $result = null;
        if ($plan['blocked'] === [] && ! (bool) $this->option('dry-run')) {
            $result = app(ContentDefinitions::class)->migrate($plan, $actor);
        }
        if ($format === 'json') {
            $this->line((string) json_encode(['plan' => $plan, 'result' => $result], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($plan['ready'] as $target) {
                $this->line("ready: {$target['definition']}:{$target['blockId']} "."{$target['fromVersion']}->{$target['toVersion']} "."revision {$target['expectedRevision']}");
            }
            foreach ($plan['blocked'] as $problem) {
                $this->line($this->problem($problem));
            }
            if ($result !== null) {
                $this->info('migrated: '.count($result['migrated']));
            }
            if ($plan['hasMore']) {
                $this->warn('More pending blocks remain; run another batch after this one.');
            }
        }

        return $plan['blocked'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /** Native Content operation.
     * @param  array{blockId:string,blockKey:string,definition:string,fromVersion:int,toVersion:int,expectedRevision:int,deleted:bool,code:string,message:string}  $problem
     */
    private function problem(array $problem): string
    {
        return "blocked: {$problem['definition']}:{$problem['blockId']} "."{$problem['fromVersion']}->{$problem['toVersion']} "."[{$problem['code']}] {$problem['message']}";
    }
}
