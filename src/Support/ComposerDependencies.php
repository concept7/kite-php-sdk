<?php

namespace Concept7\Kite\Support;

use Illuminate\Support\Facades\Process;

class ComposerDependencies
{
    public static function direct(string $phpPath = 'php'): array
    {
        $result = Process::run($phpPath.' vendor/bin/composer show -D --format=json --no-dev');
        $data = json_decode($result->output());

        if (blank($data)) {
            return [];
        }

        return $data->installed;
    }
}
