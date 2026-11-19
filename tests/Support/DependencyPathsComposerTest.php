<?php

use Concept7\Kite\Support\DependencyPaths;

$fixtures = __DIR__.'/../Fixtures/Composer';

function composerProjectWithDirectPackages(int $count): string
{
    $directory = sys_get_temp_dir().'/kite-paths-'.uniqid();
    mkdir($directory);

    $require = [];
    $packages = [];

    foreach (range(1, $count) as $number) {
        $require["acme/direct-{$number}"] = '^1.0';
        $packages[] = [
            'name' => "acme/direct-{$number}",
            'version' => '1.0.0',
            'require' => ['acme/target' => '^1.0'],
        ];
    }

    $packages[] = ['name' => 'acme/target', 'version' => '1.0.0'];

    file_put_contents($directory.'/composer.json', json_encode(['require' => $require]));
    file_put_contents($directory.'/composer.lock', json_encode(['packages' => $packages, 'packages-dev' => []]));

    return $directory;
}

test('direct dependency yields a one-hop path with the composer.json constraint', function () use ($fixtures): void {
    expect(DependencyPaths::composer($fixtures.'/basic', 'acme/other'))->toBe([
        [['name' => 'acme/other', 'version' => '1.0.0', 'constraint' => '^1.0']],
    ]);
});

test('transitive dependency yields one shortest path per direct package', function () use ($fixtures): void {
    expect(DependencyPaths::composer($fixtures.'/basic', 'symfony/http-foundation'))->toBe([
        [
            ['name' => 'laravel/framework', 'version' => 'v11.0.0', 'constraint' => '^11.0'],
            ['name' => 'symfony/http-foundation', 'version' => 'v7.1.0', 'constraint' => '^7.0.0'],
        ],
        [
            ['name' => 'acme/other', 'version' => '1.0.0', 'constraint' => '^1.0'],
            ['name' => 'symfony/http-foundation', 'version' => 'v7.1.0', 'constraint' => '~7.1'],
        ],
    ]);
});

test('require-dev roots count', function () use ($fixtures): void {
    expect(DependencyPaths::composer($fixtures.'/basic', 'symfony/console'))->toBe([
        [
            ['name' => 'acme/dev-tool', 'version' => '2.0.0', 'constraint' => '^2.0'],
            ['name' => 'symfony/console', 'version' => 'v7.0.0', 'constraint' => '^7.0'],
        ],
    ]);
});

test('cycles terminate', function () use ($fixtures): void {
    expect(DependencyPaths::composer($fixtures.'/cycle', 'acme/target'))->toBe([
        [
            ['name' => 'acme/a', 'version' => '1.0.0', 'constraint' => '^1.0'],
            ['name' => 'acme/b', 'version' => '1.1.0', 'constraint' => '^1.1'],
            ['name' => 'acme/target', 'version' => '3.2.1', 'constraint' => '^3.0'],
        ],
    ]);
});

test('unreachable package yields no paths', function () use ($fixtures): void {
    expect(DependencyPaths::composer($fixtures.'/basic', 'acme/missing'))->toBe([]);
});

test('missing or unreadable files yield null', function (): void {
    $directory = composerProjectWithDirectPackages(1);

    unlink($directory.'/composer.lock');
    expect(DependencyPaths::composer($directory, 'acme/target'))->toBeNull();

    file_put_contents($directory.'/composer.lock', '{not json');
    expect(DependencyPaths::composer($directory, 'acme/target'))->toBeNull();

    unlink($directory.'/composer.json');
    expect(DependencyPaths::composer($directory, 'acme/target'))->toBeNull();
});

test('more than 50 direct packages reaching the package yields null', function (): void {
    expect(DependencyPaths::composer(composerProjectWithDirectPackages(51), 'acme/target'))->toBeNull();
    expect(DependencyPaths::composer(composerProjectWithDirectPackages(50), 'acme/target'))->toHaveCount(50);
});

test('paths with an empty hop are dropped', function (): void {
    $directory = composerProjectWithDirectPackages(1);
    file_put_contents($directory.'/composer.lock', json_encode([
        'packages' => [
            ['name' => 'acme/direct-1', 'require' => ['acme/target' => '^1.0']],
            ['name' => 'acme/target', 'version' => '1.0.0'],
        ],
    ]));

    expect(DependencyPaths::composer($directory, 'acme/target'))->toBe([]);
});

test('base path is the project root', function (): void {
    expect(DependencyPaths::basePath())->toBe(realpath(__DIR__.'/../..'));
});

test('malformed lockfile content never throws', function (): void {
    $directory = composerProjectWithDirectPackages(1);
    file_put_contents($directory.'/composer.lock', json_encode([
        'packages' => 'x',
        'packages-dev' => [['name' => 'acme/direct-1', 'require' => ['a' => 1, 'acme/target' => ['^1.0']]], 'y'],
    ]));

    expect(DependencyPaths::composer($directory, 'acme/target'))->toBe([]);
});

test('package names are case-insensitive', function () use ($fixtures): void {
    expect(DependencyPaths::composer($fixtures.'/basic', 'Acme/Other'))->toHaveCount(1);
});
