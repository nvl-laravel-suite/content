<?php

declare(strict_types=1);

use Composer\Semver\Semver;

it('requires a patched HTML Sanitizer for standalone consumers', function (string $version, bool $accepted): void {
    $manifest = json_decode(file_get_contents(__DIR__.'/../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(Semver::satisfies($version, $manifest['require']['symfony/html-sanitizer']))->toBe($accepted);
})->with([
    'previous Symfony 7 floor' => ['7.4.13', false],
    'last affected Symfony 7 patch' => ['7.4.22', false],
    'previous Symfony 8 floor' => ['8.0.13', false],
    'last affected Symfony 8 patch' => ['8.1.10', false],
    'patched Symfony 7 floor' => ['7.4.23', true],
    'later Symfony 7 patch' => ['7.4.24', true],
    'patched Symfony 8 floor' => ['8.1.11', true],
    'later Symfony 8 minor' => ['8.2.0', true],
]);
