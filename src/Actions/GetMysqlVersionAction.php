<?php

namespace Concept7\Kite\Actions;

use Closure;
use Concept7\Kite\Contracts\ActionInterface;
use Concept7\Kite\Support\Collection;

class GetMysqlVersionAction implements ActionInterface
{
    public function handle(Collection $data, Closure $next): Collection
    {
        $output = @shell_exec('mysql --version');

        if (blank($output)) {
            return $next($data);
        }

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
