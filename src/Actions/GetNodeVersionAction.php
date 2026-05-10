<?php

namespace Concept7\Kite\Actions;

use Closure;
use Composer\InstalledVersions;
use Concept7\Kite\Contracts\ActionInterface;
use Illuminate\Support\Collection;

class GetNodeVersionAction implements ActionInterface
{
    protected string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? InstalledVersions::getRootPackage()['install_path'];
    }

    public function handle(Collection $data, Closure $next): Collection
    {
        $data->push([
            'key' => 'node_version',
            'value' => $this->resolveVersion(),
        ]);

        return $next($data);
    }

    private function resolveVersion(): ?string
    {
        $nvmrcPath = rtrim($this->projectRoot, '/').'/.nvmrc';

        if (file_exists($nvmrcPath)) {
            return ltrim(trim((string) file_get_contents($nvmrcPath)), 'v');
        }

        $packageJsonPath = rtrim($this->projectRoot, '/').'/package.json';

        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);
            $nodeVersion = data_get($packageJson, 'engines.node');

            if (filled($nodeVersion)) {
                return ltrim(trim($nodeVersion), 'v');
            }
        }

        return null;
    }
}
