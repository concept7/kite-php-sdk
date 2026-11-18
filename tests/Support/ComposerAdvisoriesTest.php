<?php

use Concept7\Kite\Http\Integrations\Packagist\Requests\GetSecurityAdvisoriesRequest;
use Concept7\Kite\Support\ComposerAdvisories;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

afterEach(fn () => MockClient::destroyGlobal());

function fakePackagistAdvisory(array $advisory): void
{
    MockClient::global([
        GetSecurityAdvisoriesRequest::class => MockResponse::make([
            'advisories' => [
                'laravel/framework' => [
                    array_merge([
                        'advisoryId' => 'PKSA-2024-0001',
                        'packageName' => 'laravel/framework',
                        'title' => 'Remote code execution vulnerability',
                        'severity' => 'high',
                    ], $advisory),
                ],
            ],
        ]),
    ]);
}

$laravelPackage = [['name' => 'laravel/framework', 'version' => '1.2.0', 'ecosystem' => 'composer']];

test('composer advisory carries the fixed version', function () use ($laravelPackage): void {
    fakePackagistAdvisory(['affectedVersions' => '>=1.0,<1.2.3']);

    $advisories = ComposerAdvisories::scan($laravelPackage);

    expect($advisories)->toHaveCount(1)
        ->and($advisories[0]['fixed_version'])->toBe('1.2.3');
});

test('composer advisory with an open range has a null fixed version', function () use ($laravelPackage): void {
    fakePackagistAdvisory(['affectedVersions' => '>=1.0']);

    $advisories = ComposerAdvisories::scan($laravelPackage);

    expect(array_key_exists('fixed_version', $advisories[0]))->toBeTrue()
        ->and($advisories[0]['fixed_version'])->toBeNull();
});

test('composer advisory without affected versions has no fixed version key', function () use ($laravelPackage): void {
    fakePackagistAdvisory([]);

    $advisories = ComposerAdvisories::scan($laravelPackage);

    expect($advisories)->toHaveCount(1)
        ->and(array_key_exists('fixed_version', $advisories[0]))->toBeFalse();
});

test('composer advisory carries dependency paths', function (): void {
    fakePackagistAdvisory(['affectedVersions' => '>=1.0,<1.2.3']);

    $advisories = ComposerAdvisories::scan(
        [['name' => 'laravel/framework', 'version' => '1.2.0', 'ecosystem' => 'composer']],
        __DIR__.'/../Fixtures/Composer/basic',
    );

    expect($advisories[0]['dependency_paths'])->toBe([
        [
            ['name' => 'laravel/framework', 'version' => 'v11.0.0', 'constraint' => '^11.0'],
        ],
    ]);
});

test('composer advisory has no dependency paths key when none are found', function (string $basePath) use ($laravelPackage): void {
    fakePackagistAdvisory(['affectedVersions' => '>=1.0,<1.2.3']);

    $advisories = ComposerAdvisories::scan($laravelPackage, $basePath);

    expect($advisories)->toHaveCount(1)
        ->and(array_key_exists('dependency_paths', $advisories[0]))->toBeFalse();
})->with([
    'no project files' => [sys_get_temp_dir()],
    'package not in lockfile' => [__DIR__.'/../Fixtures/Project'],
]);

test('a failure while building paths drops only the paths', function () use ($laravelPackage): void {
    fakePackagistAdvisory(['affectedVersions' => '>=1.0,<1.2.3']);

    $directory = sys_get_temp_dir().'/kite-unreadable-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/composer.json', '{}');
    file_put_contents($directory.'/composer.lock', '{}');
    chmod($directory.'/composer.lock', 0000);

    set_error_handler(function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        $advisories = ComposerAdvisories::scan($laravelPackage, $directory);
    } finally {
        restore_error_handler();
        chmod($directory.'/composer.lock', 0644);
        array_map('unlink', glob($directory.'/*'));
        rmdir($directory);
    }

    expect($advisories)->toHaveCount(1)
        ->and($advisories[0]['advisory_id'])->toBe('PKSA-2024-0001')
        ->and(array_key_exists('dependency_paths', $advisories[0]))->toBeFalse();
})->skip(function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can read unreadable files');
