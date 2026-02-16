<?php

namespace Concept7\Kite\Support;

use Illuminate\Process\Factory as ProcessFactory;

class ComposerDependencies
{
    public static function direct(string $phpPath = 'php'): array
    {
        $process = new ProcessFactory;
        $result = $process->run($phpPath.' vendor/bin/composer show -D --format=json --no-dev');

        if ($result->failed() || blank($result->output())) {
            return [];
        }

        $data = json_decode($result->output());

        if (blank($data)) {
            return [];
        }

        return $data->installed;
    }
}
