<?php

namespace Concept7\Kite\Support;

class ComposerDependencies
{
    public static function direct(string $phpPath = 'php'): array
    {
        $output = @shell_exec($phpPath.' vendor/bin/composer show -D --format=json --no-dev');

        if (blank($output)) {
            return [];
        }

        $data = json_decode($output);

        if (blank($data)) {
            return [];
        }

        return $data->installed;
    }
}
