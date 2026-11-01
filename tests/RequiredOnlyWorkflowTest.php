<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Filesystem\Filesystem;
use Nvl\Content\Services\ContentBlocks;
use Nvl\Content\Services\ContentDefinitions;
use Nvl\Content\Services\ContentDoctor;
use Nvl\Content\Services\ContentReader;
use Nvl\Content\Tests\StandaloneContentTestCase;
use Nvl\Support\Enums\SystemActor;
use Symfony\Component\Process\Process;

uses(StandaloneContentTestCase::class);

it('compiles synchronizes and edits localized Content without optional Media providers', function (): void {
    expect(app()->getLoadedProviders())->not->toHaveKeys(['Nvl\\Media\\Providers\\MediaServiceProvider', 'Nvl\\Ownership\\Providers\\OwnershipServiceProvider']);
    $plan = app(ContentDefinitions::class)->sync(SystemActor::Background);
    expect($plan['create'])->toBe(['standalone-copy'])->and($plan['applied'])->toBeTrue();
    $block = app(ContentBlocks::class)->create([
        'definition' => 'standalone-copy', 'key' => 'lean-copy', 'scope' => 'global', 'scopeKey' => '*',
        'translations' => ['en' => ['title' => 'Required dependencies only']],
    ], SystemActor::Background);

    expect(app(ContentReader::class)->get($block, SystemActor::Background)->translated('values', 'en')['title'])->toBe('Required dependencies only');
    expect(fn () => app(ContentReader::class)->get($block, new GenericUser(['id' => 1])))
        ->toThrow(AuthorizationException::class);
    expect(app(ContentDoctor::class)->inspect()['healthy'])->toBeTrue();
});

it('caches lean native configuration without loading optional provider classes', function (): void {
    $directory = sys_get_temp_dir().'/nvl-content-lean-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    try {
        foreach (['bootstrap/cache', 'config', 'database', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs'] as $path) {
            $files->ensureDirectoryExists($directory.'/'.$path);
        }
        touch($directory.'/database/database.sqlite');
        file_put_contents($directory.'/bootstrap/app.php', <<<'PHP'
<?php
return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Nvl\Support\Providers\LocaleServiceProvider::class,
        Nvl\Translatable\Providers\TranslatableServiceProvider::class,
        Nvl\Content\Providers\ContentServiceProvider::class,
        Nvl\Content\Tests\Fixtures\StandaloneContentServiceProvider::class,
    ])->create();
PHP);
        file_put_contents($directory.'/config/nvl-content.php', '<?php return '.var_export([
            'media' => ['enabled' => false],
            'locales' => ['available' => ['en']],
            'definitions' => config('nvl-content.definitions'),
        ], true).';');
        $autoload = dirname(InstalledVersions::getInstallPath('laravel/framework') ?? throw new RuntimeException('Laravel test dependencies are not installed.'), 2).'/autoload.php';
        $script = 'require '.var_export($autoload, true).';'.<<<'PHP'
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if ($kernel->call('config:cache') !== 0 || $kernel->call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Lean Content cache or migration failed.');
}
$app->make(Nvl\Content\Services\ContentDefinitions::class)->sync(Nvl\Support\Enums\SystemActor::Background);
echo json_encode([
    'cached' => is_file($app->getCachedConfigPath()),
    'healthy' => $app->make(Nvl\Content\Services\ContentDoctor::class)->inspect()['healthy'],
    'media_loaded' => class_exists('Nvl\Media\Models\MediaAssociation', false),
    'media_provider' => isset($app->getLoadedProviders()['Nvl\Media\Providers\MediaServiceProvider']),
], JSON_THROW_ON_ERROR);
PHP;
        $process = new Process([PHP_BINARY, '-r', $script], $directory, [
            'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database/database.sqlite',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ]);
        $process->mustRun();
        expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
            ->toBe(['cached' => true, 'healthy' => true, 'media_loaded' => false, 'media_provider' => false]);
    } finally {
        $files->deleteDirectory($directory);
    }
});
