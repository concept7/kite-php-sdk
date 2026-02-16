<?php

namespace Concept7\Kite\Actions;

use Closure;
use Concept7\Kite\Contracts\ActionInterface;
use Illuminate\Support\Collection;

class GetNodePackageVersionAction implements ActionInterface
{
    protected string $projectRoot;

    public function __construct(
        protected string $metaKey,
        protected string $nodePackageName,
        ?string $projectRoot = null,
    ) {
        $this->projectRoot = $projectRoot ?? \Composer\InstalledVersions::getRootPackage()['install_path'];
    }

    public function handle(Collection $data, Closure $next): Collection
    {
        $lockFile = rtrim($this->projectRoot, '/').'/package-lock.json';

        if (! file_exists($lockFile)) {
            return $next($data);
        }

        $packageJsonData = @file_get_contents($lockFile);

        if ($packageJsonData === false) {
            return $next($data);
        }

        $json = json_decode($packageJsonData, true);
        $version = data_get($json, "packages.node_modules/{$this->nodePackageName}.version");

        $data->push([
            'key' => $this->metaKey,
            'value' => $version,
        ]);

        return $next($data);
    }
}
