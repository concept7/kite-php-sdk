<?php

namespace Concept7\Kite\Actions;

use Closure;
use Concept7\Kite\Contracts\ActionInterface;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class GetMysqlVersionAction implements ActionInterface
{
    public function handle(Collection $data, Closure $next): Collection
    {
        $process = new Process(['mysql', '--version']);
        $process->run();

        if (! $process->isSuccessful() || blank($process->getOutput())) {
            return $next($data);
        }

        $output = $process->getOutput();

        $database = 'mysql_';
        if (str_contains(strtolower($output), 'mariadb')) {
            $database = 'mariadb_';
        }

        preg_match("@[0-9]+\.[0-9]+\.[0-9]+@", $output, $version);

        if (empty($version)) {
            return $next($data);
        }

        $database = $database.$version[0];

        $data->push([
            'key' => 'database_version',
            'value' => $database,
        ]);

        return $next($data);
    }
}
