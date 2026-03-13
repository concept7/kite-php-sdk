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
        $dependencies = array_keys(array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        ));
        $packages = [];

        foreach ($dependencies as $name) {
            $version = static::resolveVersion($name, $lockfile);

            if (blank($version)) {
                continue;
            }

            $packages[] = [
                'name' => $name,
                'version' => $version,
                'ecosystem' => Ecosystem::Npm,
            ];
        }

        return $packages;
    }

    private static function resolveVersion(string $name, ?array $lockfile): ?string
    {
        if (blank($lockfile['packages'] ?? null)) {
            return null;
        }

        foreach ($lockfile['packages'] as $path => $info) {
            if (str_ends_with($path, "node_modules/{$name}")) {
                return $info['version'] ?? null;
            }
        }

        return null;
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
