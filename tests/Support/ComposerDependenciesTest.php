<?php

use Concept7\Kite\Enums\Ecosystem;
use Concept7\Kite\Support\ComposerDependencies;

$fixtures = __DIR__.'/../Fixtures/Composer';

test('all returns exactly the packages of the lockfile at the base path', function () use ($fixtures): void {
    $packages = collect(ComposerDependencies::all($fixtures.'/basic'))->keyBy('name');

    expect($packages->keys()->sort()->values()->all())->toBe([
        'acme/dev-tool',
        'acme/other',
        'laravel/framework',
        'symfony/console',
        'symfony/http-foundation',
    ])
        ->and($packages['laravel/framework']['version'])->toBe('v11.0.0')
        ->and($packages['laravel/framework']['ecosystem'])->toBe(Ecosystem::Composer)
        ->and($packages['acme/dev-tool']['is_direct'])->toBeTrue()
        ->and($packages['symfony/console']['is_direct'])->toBeFalse()
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
