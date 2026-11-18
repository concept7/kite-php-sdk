<?php

namespace Concept7\Kite\Support;

use Composer\InstalledVersions;

class DependencyPaths
{
    private const MAX_DIRECT_PACKAGES = 50;

    /**
     * @return list<list<array{name: string, version: string, constraint: string}>>|null
     */
    public static function composer(string $basePath, string $package): ?array
    {
        $composerJson = static::readJsonFile($basePath.'/composer.json');
        $lockfile = static::readJsonFile($basePath.'/composer.lock');

        if ($composerJson === null || $lockfile === null) {
            return null;
        }

        $graph = [];

        foreach (array_merge(data_get($lockfile, 'packages', []), data_get($lockfile, 'packages-dev', [])) as $entry) {
            $name = data_get($entry, 'name');

            if (blank($name)) {
                continue;
            }

            $graph[$name] = [
                'version' => (string) data_get($entry, 'version'),
                'dependencies' => static::withoutPlatformPackages((array) data_get($entry, 'require', [])),
            ];
        }

        $roots = static::withoutPlatformPackages(array_merge(
            (array) data_get($composerJson, 'require', []),
            (array) data_get($composerJson, 'require-dev', []),
        ));

        return static::shortestPaths($roots, $graph, $package);
    }

    public static function basePath(): string
    {
        $installPath = data_get(InstalledVersions::getRootPackage(), 'install_path');
        $realPath = filled($installPath) ? realpath($installPath) : false;

        return $realPath !== false ? $realPath : getcwd();
    }

    /**
     * @param  array<string, string>  $roots
     * @param  array<string, array{version: string, dependencies: array<string, string>}>  $graph
     * @return list<list<array{name: string, version: string, constraint: string}>>|null
     */
    protected static function shortestPaths(array $roots, array $graph, string $package): ?array
    {
        $paths = [];

        foreach ($roots as $rootName => $rootConstraint) {
            $queue = [[static::hop($rootName, $rootConstraint, $graph)]];
            $visited = [$rootName => true];
            $shortestPath = null;

            while ($queue !== [] && $shortestPath === null) {
                $path = array_shift($queue);
                $current = end($path);

                if ($current['name'] === $package) {
                    $shortestPath = $path;

                    continue;
                }

                foreach (data_get($graph, [$current['name'], 'dependencies'], []) as $dependencyName => $dependencyConstraint) {
                    if (isset($visited[$dependencyName])) {
                        continue;
                    }

                    $visited[$dependencyName] = true;
                    $queue[] = [...$path, static::hop($dependencyName, $dependencyConstraint, $graph)];
                }
            }

            if ($shortestPath !== null) {
                $paths[] = $shortestPath;
            }
        }

        if (count($paths) > self::MAX_DIRECT_PACKAGES) {
            return null;
        }

        return array_values(array_filter(
            $paths,
            fn (array $path): bool => collect($path)->every(
                fn (array $hop): bool => filled($hop['name']) && filled($hop['version']) && filled($hop['constraint']),
            ),
        ));
    }

    /**
     * @param  array<string, array{version: string, dependencies: array<string, string>}>  $graph
     * @return array{name: string, version: string, constraint: string}
     */
    protected static function hop(string $name, string $constraint, array $graph): array
    {
        return [
            'name' => $name,
            'version' => (string) data_get($graph, [$name, 'version'], ''),
            'constraint' => $constraint,
        ];
    }

    /**
     * @param  array<string, string>  $requirements
     * @return array<string, string>
     */
    protected static function withoutPlatformPackages(array $requirements): array
    {
        return array_filter(
            $requirements,
            fn (string $name): bool => ! in_array($name, ['php', 'composer-plugin-api'], true)
                && ! str_starts_with($name, 'ext-')
                && ! str_starts_with($name, 'lib-'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return array<mixed>|null
     */
    protected static function readJsonFile(string $path): ?array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }
}
