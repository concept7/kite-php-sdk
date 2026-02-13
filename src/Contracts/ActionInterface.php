<?php

namespace Concept7\Kite\Contracts;

use Closure;
use Concept7\Kite\Support\Collection;

interface ActionInterface
{
    public function handle(Collection $data, Closure $next): Collection;
}
