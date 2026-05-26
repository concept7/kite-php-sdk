<?php

namespace Concept7\Kite\Support;

use Concept7\Kite\Enums\Ecosystem;

class NpmDependencies
{
    public static function installed(?string $basePath = null): array
    {
        $basePath ??= getcwd();

        $packageJson = static::readJsonFile($basePath.'/package.json');

        if (blank($packageJson)) {
            return [];
        }

        $lockfile = static::readJsonFile($basePath.'/package-lock.json');

        if (blank($lockfile['packages'] ?? null)) {
            return [];
        }

        $directNames = array_keys(array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        ));

        $requiredByMap = static::buildRequiredByMap($lockfile['packages']);

        $packages = [];

        foreach ($lockfile['packages'] as $path => $info) {
            if ($path === '') {
                continue;
            }

            $name = static::nameFromPath($path);
            $version = $info['version'] ?? null;

            if (blank($name) || blank($version)) {
                continue;
            }

            $packages[$name.':'.$version] = [
                'name' => $name,
                'version' => $version,
                'ecosystem' => Ecosystem::Npm,
                'is_direct' => in_array($name, $directNames),
                'required_by' => $requiredByMap[$name] ?? [],
            ];
        }

        return array_values($packages);
    }

    private static function buildRequiredByMap(array $lockfilePackages): array
    {
        $requiredBy = [];

        foreach ($lockfilePackages as $path => $info) {
            if ($path === '') {
                continue;
            }

            $name = static::nameFromPath($path);

            if (blank($name)) {
                continue;
            }

            foreach (array_keys($info['dependencies'] ?? []) as $dependency) {
                $requiredBy[$dependency][] = $name;
            }
        }

        return $requiredBy;
    }

    private static function nameFromPath(string $path): ?string
    {
        $parts = explode('node_modules/', $path);
        $name = end($parts);

        return filled($name) ? $name : null;
    }

    private static function readJsonFile(string $path): ?array
    {
        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }
}
