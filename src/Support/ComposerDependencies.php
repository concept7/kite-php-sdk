<?php

namespace Concept7\Kite\Support;

use Composer\InstalledVersions;
use Concept7\Kite\Enums\Ecosystem;

class ComposerDependencies
{
    public static function direct(?string $basePath = null): array
    {
        $basePath ??= getcwd();
        $composerJsonPath = $basePath.'/composer.json';

        if (! file_exists($composerJsonPath)) {
            return [];
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        if (blank($composerJson)) {
            return [];
        }

        $requires = array_keys($composerJson['require'] ?? []);

        $packages = [];

        foreach ($requires as $name) {
            if ($name === 'php' || str_starts_with($name, 'ext-') || str_starts_with($name, 'lib-')) {
                continue;
            }

            if (! InstalledVersions::isInstalled($name)) {
                continue;
            }

            $packages[] = static::parsePackageData($name, true, []);
        }

        return $packages;
    }

    public static function all(?string $basePath = null): array
    {
        $basePath ??= getcwd();
        $directNames = static::directNames($basePath);
        $requiredByMap = static::buildRequiredByMap($basePath);

        return collect(InstalledVersions::getAllRawData()[0]['versions'])
            ->filter(fn (array $properties): bool => isset($properties['pretty_version']))
            ->map(fn (array $properties, string $name) => static::parsePackageData(
                $name,
                in_array($name, $directNames),
                $requiredByMap[$name] ?? [],
            ))
            ->values()
            ->all();
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

    private static function parsePackageData(string $name, bool $isDirect = false, array $requiredBy = []): array
    {
        return [
            'name' => $name,
            'version' => InstalledVersions::getPrettyVersion($name),
            'ecosystem' => Ecosystem::Composer,
            'is_direct' => $isDirect,
            'required_by' => $requiredBy,
        ];
    }
}
