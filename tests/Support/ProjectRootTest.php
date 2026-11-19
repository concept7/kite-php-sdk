<?php

use Concept7\Kite\Support\DependencyPaths;

function fakeSdkInstallation(string $vendorDirectory = 'vendor'): string
{
    $projectDirectory = realpath(sys_get_temp_dir()).'/kite-root-'.uniqid().'/project';
    $sdkDirectory = $projectDirectory.'/'.$vendorDirectory.'/concept7/kite-php-sdk/src/Support';
    mkdir($sdkDirectory, 0777, true);

    return $projectDirectory;
}

test('project root is the directory before the vendor path of the sdk', function (): void {
    $projectDirectory = fakeSdkInstallation();

    expect(DependencyPaths::projectRootFrom($projectDirectory.'/vendor/concept7/kite-php-sdk/src/Support'))
        ->toBe($projectDirectory);
});

test('project root ignores vendor directories of other packages deeper in the tree', function (): void {
    $projectDirectory = fakeSdkInstallation();
    $nestedDirectory = $projectDirectory.'/vendor/concept7/kite-php-sdk/vendor/concept7/kite-php-sdk/src';
    mkdir($nestedDirectory, 0777, true);

    expect(DependencyPaths::projectRootFrom($nestedDirectory))->toBe($projectDirectory);
});

test('project root follows a custom vendor directory through installed.json', function (): void {
    $projectDirectory = fakeSdkInstallation('libraries');
    mkdir($projectDirectory.'/libraries/composer', 0777, true);
    file_put_contents($projectDirectory.'/composer.json', '{}');
    file_put_contents(
        $projectDirectory.'/libraries/composer/installed.json',
        json_encode(['packages' => [['name' => 'concept7/kite-php-sdk']]]),
    );

    expect(DependencyPaths::projectRootFrom($projectDirectory.'/libraries/concept7/kite-php-sdk/src/Support'))
        ->toBe($projectDirectory);
});

test('project root is null when the sdk is not installed in a vendor directory', function (): void {
    expect(DependencyPaths::projectRootFrom(__DIR__))->toBeNull();
});

test('base path falls back to the working directory for a development checkout', function (): void {
    expect(DependencyPaths::basePath())->toBe(getcwd());
});
