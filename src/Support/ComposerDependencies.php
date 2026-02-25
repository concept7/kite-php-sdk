<?php

namespace Concept7\Kite\Support;

use Illuminate\Process\Factory as ProcessFactory;

class ComposerDependencies
{
    public static function direct(string $phpPath = 'php', ?string $basePath = null): array
    {
        $process = new ProcessFactory;
        $pending = $basePath !== null ? $process->path($basePath) : $process;
        $result = $pending->run($phpPath.' vendor/bin/composer show -D --format=json --no-dev');

        exit($basePath);

        var_dump($basePath);
        var_dump($phpPath.' vendor/bin/composer show -D --format=json --no-dev');

        if ($result->failed() || blank($result->output())) {
            return [];
        }

        $data = json_decode($result->output());

        var_dump($data);

        if (blank($data)) {
            return [];
        }

        return $data->installed;
    }
}
