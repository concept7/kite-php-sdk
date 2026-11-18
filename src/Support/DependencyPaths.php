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

        $entries = array_merge(
            static::arrayAt($lockfile, 'packages'),
            static::arrayAt($lockfile, 'packages-dev'),
        );

        foreach ($entries as $entry) {
            $name = is_array($entry) ? data_get($entry, 'name') : null;

            if (! is_string($name) || blank($name)) {
                continue;
            }

            $version = data_get($entry, 'version');

            $graph[strtolower($name)] = [
                'version' => is_string($version) ? $version : '',
                'dependencies' => static::cleanRequirements(static::arrayAt($entry, 'require')),
            ];
        }

        $roots = static::cleanRequirements(array_merge(
            static::arrayAt($composerJson, 'require'),
            static::arrayAt($composerJson, 'require-dev'),
        ));

        return static::shortestPaths($roots, $graph, strtolower($package));
    }

    public static function basePath(): string
    {
        $installPath = data_get(InstalledVersions::getRootPackage(), 'install_path');
        $realPath = filled($installPath) ? realpath($installPath) : false;

        return $realPath !== false ? $realPath : (getcwd() ?: '.');
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
     * @param  array<mixed>  $requirements
     * @return array<string, string>
     */
    protected static function cleanRequirements(array $requirements): array
    {
        $cleaned = [];

        foreach ($requirements as $name => $constraint) {
            if (! is_string($name) || ! is_string($constraint) || blank($name) || blank($constraint)) {
                continue;
            }

            $name = strtolower($name);

            if (in_array($name, ['php', 'composer-plugin-api'], true) || str_starts_with($name, 'ext-') || str_starts_with($name, 'lib-')) {
                continue;
            }

            $cleaned[$name] = $constraint;
        }

        return $cleaned;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected static function arrayAt(array $data, string $key): array
    {
        $value = data_get($data, $key);

        return is_array($value) ? $value : [];
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
