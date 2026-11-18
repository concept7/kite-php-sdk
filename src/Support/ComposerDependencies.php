<?php

namespace Concept7\Kite\Support;

use Composer\InstalledVersions;
use Concept7\Kite\Enums\Ecosystem;

class ComposerDependencies
{
    public static function direct(?string $basePath = null): array
    {
        $basePath ??= DependencyPaths::basePath();
        $composerJsonPath = $basePath.'/composer.json';

        if (! file_exists($composerJsonPath)) {
            return [];
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        if (blank($composerJson)) {
            return [];
        }

        $requires = array_keys($composerJson['require'] ?? []);
        $lockedVersions = static::lockedVersions($basePath);

        $packages = [];

        foreach ($requires as $name) {
            if ($name === 'php' || str_starts_with($name, 'ext-') || str_starts_with($name, 'lib-')) {
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
        $directNames = static::directNames($basePath);
        $requiredByMap = static::buildRequiredByMap($basePath);
        $versions = static::lockedVersions($basePath);

        if ($versions === null) {
            $versions = [];

            foreach (InstalledVersions::getAllRawData() as $data) {
                foreach ($data['versions'] ?? [] as $name => $properties) {
                    if (isset($properties['pretty_version'])) {
                        $versions[$name] ??= $properties['pretty_version'];
                    }
                }
            }
        }

        return collect($versions)
            ->map(fn (string $version, string $name) => static::parsePackageData(
                $name,
                $version,
                in_array($name, $directNames),
                $requiredByMap[$name] ?? [],
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    private static function lockedVersions(string $basePath): ?array
    {
        $lockfilePath = $basePath.'/composer.lock';

        if (! is_file($lockfilePath)) {
            return null;
        }

        $lockfile = json_decode((string) file_get_contents($lockfilePath), true);

        if (! is_array($lockfile)) {
            return null;
        }

        $versions = [];

        foreach (array_merge($lockfile['packages'] ?? [], $lockfile['packages-dev'] ?? []) as $package) {
            $name = data_get($package, 'name');
            $version = data_get($package, 'version');

            if (filled($name) && filled($version)) {
                $versions[$name] = $version;
            }
        }

        return $versions;
    }

    private static function buildRequiredByMap(string $basePath): array
    {
        $lockfilePath = $basePath.'/composer.lock';

        if (! file_exists($lockfilePath)) {
            return [];
        }

        $lockfile = json_decode(file_get_contents($lockfilePath), true);

        if (blank($lockfile)) {
            return [];
        }

        $requiredBy = [];

        $allPackages = array_merge(
            $lockfile['packages'] ?? [],
            $lockfile['packages-dev'] ?? [],
        );

        foreach ($allPackages as $package) {
            foreach (array_keys($package['require'] ?? []) as $dependency) {
                if ($dependency === 'php' || str_starts_with($dependency, 'ext-') || str_starts_with($dependency, 'lib-')) {
                    continue;
                }

                $requiredBy[$dependency][] = $package['name'];
            }
        }

        return $requiredBy;
    }

    private static function directNames(string $basePath): array
    {
        $composerJsonPath = $basePath.'/composer.json';

        if (! file_exists($composerJsonPath)) {
            return [];
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        if (blank($composerJson)) {
            return [];
        }

        return array_keys(array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        ));
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
