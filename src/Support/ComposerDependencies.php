<?php

namespace Concept7\Kite\Support;

use Composer\InstalledVersions;

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

            $packages[] = [
                'name' => $name,
                'version' => InstalledVersions::getPrettyVersion($name),
            ];
        }

        return $packages;
    }

    public static function all(): array
    {
        return collect(InstalledVersions::getAllRawData()[0]['versions'])
            ->filter(fn (array $properties) => isset($properties['pretty_version']))
            ->map(fn (array $properties, string $name) => ['name' => $name, 'version' => $properties['pretty_version']])
            ->values()
            ->all();
    }
}
