<?php

declare(strict_types=1);

namespace Nvl\Content\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Nvl\Content\Services\ContentDoctor;

/**
 * Renders the package-owned read-only installation diagnostics.
 */
final class ContentDoctorCommand extends Command
{
    protected $signature = 'nvl:content:doctor
        {--strict : Return a non-zero status when any required check fails}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Inspect the NVL Content installation without changing state';

    public function handle(ContentDoctor $doctor): int
    {

        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            throw new InvalidArgumentException(
                'The content doctor format must be text or json.',
            );
        }

        $checks = $doctor->inspect();
        $healthy = $checks['healthy'];

        if ($format === 'json') {
            $this->line((string) json_encode($checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $check => $value) {
                $this->line(sprintf(
                    '%-34s %s',
                    $check,
                    json_encode($value, JSON_THROW_ON_ERROR),
                ));
            }
        }

        return $healthy || ! $this->option('strict') ? self::SUCCESS : self::FAILURE;
    }
}
