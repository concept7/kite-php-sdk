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
        $binaryVersion = $this->resolveVersionFromBinary();

        if (filled($binaryVersion)) {
            return $binaryVersion;
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

    protected function resolveVersionFromBinary(): ?string
    {
        $output = shell_exec('node --version 2>/dev/null');

        if (blank($output)) {
            return null;
        }

        $version = ltrim(trim($output), 'v');

        return preg_match('/^\d+\.\d+/', $version) ? $version : null;
    }
}
