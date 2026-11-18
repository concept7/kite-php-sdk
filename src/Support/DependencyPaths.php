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

    /**
     * @return list<list<array{name: string, version: string, constraint: string}>>|null
     */
    public static function npm(string $basePath, string $package, string $version): ?array
    {
        $packageJson = static::readJsonFile($basePath.'/package.json');
        $lockfile = static::readJsonFile($basePath.'/package-lock.json');

        if ($packageJson === null || $lockfile === null) {
            return null;
        }

        $lockfilePackages = static::arrayAt($lockfile, 'packages');
        $graph = [];

        foreach ($lockfilePackages as $path => $entry) {
            $path = (string) $path;
            $name = $path === '' ? null : NpmDependencies::nameFromPath($path);

            if (blank($name) || ! is_array($entry)) {
                continue;
            }

            $entryVersion = data_get($entry, 'version');
            $aliasedName = data_get($entry, 'name');

            $graph[$path] = [
                'name' => is_string($aliasedName) && filled($aliasedName) ? $aliasedName : $name,
                'version' => is_string($entryVersion) ? $entryVersion : '',
                'dependencies' => static::resolveNpmSpecs(
                    $path,
                    array_merge(
                        static::arrayAt($entry, 'dependencies'),
                        static::arrayAt($entry, 'optionalDependencies'),
                        static::arrayAt($entry, 'peerDependencies'),
                    ),
                    $lockfilePackages,
                ),
            ];
        }

        $roots = static::resolveNpmSpecs(
            '',
            array_merge(
                static::arrayAt($packageJson, 'dependencies'),
                static::arrayAt($packageJson, 'devDependencies'),
                static::arrayAt($packageJson, 'optionalDependencies'),
                static::arrayAt($packageJson, 'peerDependencies'),
            ),
            $lockfilePackages,
        );

        return static::shortestPaths($roots, $graph, $package, $version);
    }

    /**
     * @param  array<mixed>  $specs
     * @param  array<mixed>  $lockfilePackages
     * @return array<string, string>
     */
    protected static function resolveNpmSpecs(string $parentPath, array $specs, array $lockfilePackages): array
    {
        $resolved = [];

        foreach ($specs as $name => $spec) {
            if (! is_string($name) || ! is_string($spec) || blank($name) || blank($spec)) {
                continue;
            }

            $searchPath = $parentPath;

            while (true) {
                $candidate = ($searchPath === '' ? '' : $searchPath.'/').'node_modules/'.$name;

                if (is_array(data_get($lockfilePackages, [$candidate]))) {
                    $resolved[$candidate] = $spec;

                    break;
                }

                if ($searchPath === '') {
                    break;
                }

                $nestedPosition = strrpos($searchPath, '/node_modules/');
                $searchPath = $nestedPosition === false ? '' : substr($searchPath, 0, $nestedPosition);
            }
        }

        return $resolved;
    }

    public static function basePath(): string
    {
        $installPath = data_get(InstalledVersions::getRootPackage(), 'install_path');
        $realPath = filled($installPath) ? realpath($installPath) : false;

        return $realPath !== false ? $realPath : (getcwd() ?: '.');
    }

    /**
     * @param  array<string, string>  $roots
     * @param  array<string, array{version: string, dependencies: array<string, string>, name?: string}>  $graph
     * @return list<list<array{name: string, version: string, constraint: string}>>|null
     */
    protected static function shortestPaths(array $roots, array $graph, string $package, ?string $version = null): ?array
    {
        $paths = [];

        foreach ($roots as $rootKey => $rootConstraint) {
            $rootKey = (string) $rootKey;
            $queue = [[$rootKey, [static::hop($rootKey, $rootConstraint, $graph)]]];
            $visited = [$rootKey => true];
            $shortestPath = null;

            while ($queue !== [] && $shortestPath === null) {
                [$currentKey, $path] = array_shift($queue);
                $current = end($path);

                if ($current['name'] === $package && ($version === null || $current['version'] === $version)) {
                    $shortestPath = $path;

                    continue;
                }

                foreach (data_get($graph, [$currentKey, 'dependencies'], []) as $dependencyKey => $dependencyConstraint) {
                    $dependencyKey = (string) $dependencyKey;

                    if (isset($visited[$dependencyKey])) {
                        continue;
                    }

                    $visited[$dependencyKey] = true;
                    $queue[] = [$dependencyKey, [...$path, static::hop($dependencyKey, $dependencyConstraint, $graph)]];
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
     * @param  array<string, array{version: string, dependencies: array<string, string>, name?: string}>  $graph
     * @return array{name: string, version: string, constraint: string}
     */
    protected static function hop(string $key, string $constraint, array $graph): array
    {
        return [
            'name' => (string) data_get($graph, [$key, 'name'], $key),
            'version' => (string) data_get($graph, [$key, 'version'], ''),
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
