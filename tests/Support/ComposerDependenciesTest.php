<?php

use Concept7\Kite\Enums\Ecosystem;
use Concept7\Kite\Support\ComposerDependencies;

$fixtures = __DIR__.'/../Fixtures/Composer';

test('all returns the production packages of the lockfile at the base path', function () use ($fixtures): void {
    $packages = collect(ComposerDependencies::all($fixtures.'/basic'))->keyBy('name');

    expect($packages->keys()->sort()->values()->all())->toBe([
        'acme/other',
        'laravel/framework',
        'symfony/http-foundation',
    ])
        ->and($packages['laravel/framework']['version'])->toBe('v11.0.0')
        ->and($packages['laravel/framework']['ecosystem'])->toBe(Ecosystem::Composer)
        ->and($packages['acme/other']['is_direct'])->toBeTrue()
        ->and($packages['symfony/http-foundation']['is_direct'])->toBeFalse()
        ->and($packages['symfony/http-foundation']['required_by'])->toBe([
            'laravel/framework',
            'acme/other',
        ]);
});

test('all falls back to the installed packages without a lockfile', function (): void {
    $directory = sys_get_temp_dir().'/kite-no-lock-'.uniqid();
    mkdir($directory);

    $names = array_column(ComposerDependencies::all($directory), 'name');

    expect($names)->toContain('composer/semver')
        ->and($names)->not->toContain('acme/other');
});

test('direct returns the required packages of the lockfile at the base path', function () use ($fixtures): void {
    $packages = ComposerDependencies::direct($fixtures.'/basic');

    expect(array_column($packages, 'name'))->toBe(['laravel/framework', 'acme/other'])
        ->and($packages[0]['version'])->toBe('v11.0.0')
        ->and($packages[0]['is_direct'])->toBeTrue();
});

function composerProjectWithDevPackage(?array $installedPackages): string
{
    $directory = sys_get_temp_dir().'/kite-dev-'.uniqid();
    mkdir($directory.'/vendor/composer', 0777, true);

    file_put_contents($directory.'/composer.json', json_encode([
        'require' => ['acme/prod' => '^1.0'],
        'require-dev' => ['acme/dev' => '^1.0'],
    ]));
    file_put_contents($directory.'/composer.lock', json_encode([
        'packages' => [['name' => 'acme/prod', 'version' => '1.0.0']],
        'packages-dev' => [['name' => 'acme/dev', 'version' => '1.0.0']],
    ]));

    if ($installedPackages !== null) {
        file_put_contents($directory.'/vendor/composer/installed.json', json_encode(['packages' => $installedPackages]));
    }

    return $directory;
}

test('dev packages missing from the project vendor are not reported', function (): void {
    $directory = composerProjectWithDevPackage([['name' => 'acme/prod']]);

    expect(array_column(ComposerDependencies::all($directory), 'name'))->toBe(['acme/prod']);
});

test('dev packages are dropped when installed.json is missing', function (): void {
    $directory = composerProjectWithDevPackage(null);

    expect(array_column(ComposerDependencies::all($directory), 'name'))->toBe(['acme/prod']);
});

test('dev packages installed in the project vendor are reported', function (): void {
    $directory = composerProjectWithDevPackage([['name' => 'acme/prod'], ['name' => 'acme/dev']]);

    expect(array_column(ComposerDependencies::all($directory), 'name'))->toBe(['acme/prod', 'acme/dev']);
});
