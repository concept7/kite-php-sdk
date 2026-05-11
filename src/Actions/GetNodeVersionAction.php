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
        $output = shell_exec('node --version 2>/dev/null');

        if (filled($output)) {
            $version = ltrim(trim($output), 'v');

            if (preg_match('/^\d+\.\d+/', $version)) {
                return $version;
            }
        }

        $nvmrcPath = rtrim($this->projectRoot, '/').'/.nvmrc';

        if (file_exists($nvmrcPath)) {
            $version = ltrim(trim((string) file_get_contents($nvmrcPath)), 'v');

            if (preg_match('/^\d+\.\d+/', $version)) {
                return $version;
            }
        }

        return null;
    }
}
