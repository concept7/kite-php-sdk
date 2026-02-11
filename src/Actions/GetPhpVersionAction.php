<?php

namespace Concept7\Kite\Actions;

use Closure;
use Concept7\Kite\Contracts\ActionInterface;
use Illuminate\Support\Collection;

class GetPhpVersionAction implements ActionInterface
{
    public function handle(Collection $data, Closure $next): Collection
    {
        $data->push([
            'key' => 'php_version',
            'value' => phpversion(),
        ]);

        return $next($data);
    }
}
