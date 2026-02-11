<?php

namespace Concept7\Kite\Actions;

use Closure;
use Concept7\Kite\Contracts\ActionInterface;
use Illuminate\Support\Collection;

class GetNodePackageVersionAction implements ActionInterface
{
    public function __construct(
        protected string $projectRoot,
        protected string $metaKey,
        protected string $nodePackageName,
    ) {}

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
