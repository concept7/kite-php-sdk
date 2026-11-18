<?php

use Concept7\Kite\Support\DependencyPaths;

$fixtures = __DIR__.'/../Fixtures/Npm';

test('hoisted transitive dependency yields a path through the direct package', function () use ($fixtures): void {
    $paths = DependencyPaths::npm($fixtures.'/basic', 'picomatch', '4.0.2');

    expect($paths)->toContain([
        ['name' => 'vite', 'version' => '6.0.1', 'constraint' => '^6.0.0'],
        ['name' => 'rollup', 'version' => '4.5.0', 'constraint' => '^4.0.0'],
        ['name' => 'picomatch', 'version' => '4.0.2', 'constraint' => '^4.0.0'],
    ]);
});

test('devDependencies and peerDependencies count as edges', function () use ($fixtures): void {
    $paths = DependencyPaths::npm($fixtures.'/basic', 'picomatch', '4.0.2');

    expect($paths)->toContain([
        ['name' => 'peer-host', 'version' => '1.0.0', 'constraint' => '^1.0.0'],
        ['name' => 'picomatch', 'version' => '4.0.2', 'constraint' => '^4.0.0'],
    ]);

    expect(DependencyPaths::npm($fixtures.'/basic', '@acme/tool', '1.2.0'))->toBe([
        [['name' => '@acme/tool', 'version' => '1.2.0', 'constraint' => '^1.0.0']],
        [
            ['name' => 'eslint', 'version' => '9.1.0', 'constraint' => '^9.0.0'],
            ['name' => '@acme/tool', 'version' => '1.2.0', 'constraint' => '^1.0.0'],
        ],
    ]);
});

test('npm alias spec is passed through verbatim as the constraint', function () use ($fixtures): void {
    $paths = DependencyPaths::npm($fixtures.'/basic', 'picomatch', '4.0.2');

    expect($paths)->toContain([
        ['name' => 'picomatch', 'version' => '4.0.2', 'constraint' => 'npm:picomatch@^4'],
    ]);
});

test('nested vulnerable copy is reached through its parent, not the root copy', function () use ($fixtures): void {
    expect(DependencyPaths::npm($fixtures.'/nested', 'picomatch', '2.3.1'))->toBe([
        [
            ['name' => 'chokidar', 'version' => '3.6.0', 'constraint' => '^3.0.0'],
            ['name' => 'picomatch', 'version' => '2.3.1', 'constraint' => '^2.0.0'],
        ],
    ]);

    expect(DependencyPaths::npm($fixtures.'/nested', 'picomatch', '4.0.2'))->toBe([
        [['name' => 'picomatch', 'version' => '4.0.2', 'constraint' => '^4.0.0']],
    ]);
});

test('cycles terminate', function () use ($fixtures): void {
    expect(DependencyPaths::npm($fixtures.'/cycle', 'gamma', '1.0.0'))->toBe([
        [
            ['name' => 'alpha', 'version' => '1.0.0', 'constraint' => '^1.0.0'],
            ['name' => 'beta', 'version' => '1.0.0', 'constraint' => '^1.0.0'],
            ['name' => 'gamma', 'version' => '1.0.0', 'constraint' => '^1.0.0'],
        ],
    ]);

    expect(DependencyPaths::npm($fixtures.'/cycle', 'missing', '1.0.0'))->toBe([]);
});

test('missing lockfile returns null', function (): void {
    $directory = sys_get_temp_dir().'/kite-npm-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/package.json', '{}');

    expect(DependencyPaths::npm($directory, 'picomatch', '4.0.2'))->toBeNull();
});

test('malformed lockfile sections do not throw', function (): void {
    $directory = sys_get_temp_dir().'/kite-npm-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/package.json', json_encode(['dependencies' => ['a' => 1, 'b' => '^1.0.0'], 'devDependencies' => 'nope']));
    file_put_contents($directory.'/package-lock.json', json_encode(['packages' => ['node_modules/b' => ['version' => '1.0.0', 'dependencies' => 'nope'], 'node_modules/c' => 'nope']]));

    expect(DependencyPaths::npm($directory, 'b', '1.0.0'))->toBe([
        [['name' => 'b', 'version' => '1.0.0', 'constraint' => '^1.0.0']],
    ]);
});
