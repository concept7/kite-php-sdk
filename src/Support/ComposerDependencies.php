<?php

namespace Concept7\Kite\Support;

use Composer\InstalledVersions;
use Concept7\Kite\Enums\Ecosystem;

class ComposerDependencies
{
    public static function direct(?string $basePath = null): array
    {
        $basePath ??= DependencyPaths::basePath();
        $composerJson = static::readJsonFile($basePath.'/composer.json');

        if (blank($composerJson)) {
            return [];
        }

        $lockEntries = static::lockEntries($basePath);
        $lockedVersions = $lockEntries === null ? null : array_column($lockEntries, 'version', 'name');

        $packages = [];

        foreach (array_keys(data_get($composerJson, 'require', [])) as $name) {
            if (static::isPlatformRequirement($name)) {
                continue;
            }

            if ($lockedVersions === null && ! InstalledVersions::isInstalled($name)) {
                continue;
            }

            if ($lockedVersions !== null && ! array_key_exists($name, $lockedVersions)) {
                continue;
            }

            $packages[] = static::parsePackageData(
                $name,
                $lockedVersions[$name] ?? InstalledVersions::getPrettyVersion($name),
                true,
                [],
            );
        }

        return $packages;
    }

    public static function all(?string $basePath = null): array
    {
        $basePath ??= DependencyPaths::basePath();
        $composerJson = static::readJsonFile($basePath.'/composer.json');
        $directNames = array_keys(array_merge(
            data_get($composerJson, 'require', []),
            data_get($composerJson, 'require-dev', []),
        ));
        $lockEntries = static::lockEntries($basePath);

        if ($lockEntries === null) {
            return static::installedPackages($directNames);
        }

        $requiredByMap = [];

        foreach ($lockEntries as $entry) {
            foreach (array_keys(data_get($entry, 'require', [])) as $dependency) {
                if (! static::isPlatformRequirement($dependency)) {
                    $requiredByMap[$dependency][] = $entry['name'];
                }
            }
        }

        return array_map(
            fn (array $entry): array => static::parsePackageData(
                $entry['name'],
                $entry['version'],
                in_array($entry['name'], $directNames),
                $requiredByMap[$entry['name']] ?? [],
            ),
            $lockEntries,
        );
    }

    /**
     * @param  list<string>  $directNames
     */
    private static function installedPackages(array $directNames): array
    {
        $versions = [];

        foreach (InstalledVersions::getAllRawData() as $data) {
            foreach ($data['versions'] ?? [] as $name => $properties) {
                if (isset($properties['pretty_version'])) {
                    $versions[$name] ??= $properties['pretty_version'];
                }
            }
        }

        return collect($versions)
            ->map(fn (string $version, string $name): array => static::parsePackageData(
                $name,
                $version,
                in_array($name, $directNames),
                [],
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, version: string, require?: array<string, string>}>|null
     */
    private static function lockEntries(string $basePath): ?array
    {
        $lockfile = static::readJsonFile($basePath.'/composer.lock');

        if ($lockfile === null) {
            return null;
        }

        $installedJson = static::readJsonFile($basePath.'/vendor/composer/installed.json');
        $installedNames = array_column(data_get($installedJson, 'packages', $installedJson ?? []), 'name');

        $devEntries = array_filter(
            data_get($lockfile, 'packages-dev', []),
            fn (array $entry): bool => in_array(data_get($entry, 'name'), $installedNames, true),
        );

        return array_values(array_filter(
            array_merge(data_get($lockfile, 'packages', []), $devEntries),
            fn (array $entry): bool => filled(data_get($entry, 'name')) && filled(data_get($entry, 'version')),
        ));
    }

    private static function isPlatformRequirement(string $name): bool
    {
        return $name === 'php' || str_starts_with($name, 'ext-') || str_starts_with($name, 'lib-');
    }

    /**
     * @return array<mixed>|null
     */
    private static function readJsonFile(string $path): ?array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        $data = $contents === false ? null : json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    private static function parsePackageData(string $name, ?string $version, bool $isDirect = false, array $requiredBy = []): array
    {
        return [
            'name' => $name,
            'version' => $version,
            'ecosystem' => Ecosystem::Composer,
            'is_direct' => $isDirect,
            'required_by' => $requiredBy,
        ];
    }
}
