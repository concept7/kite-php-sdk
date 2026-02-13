<?php

namespace Concept7\Kite\Support;

use Closure;

class Pipeline
{
    protected mixed $passable;

    protected array $pipes = [];

    public function send(mixed $passable): self
    {
        $this->passable = $passable;

        return $this;
    }

    public function through(array $pipes): self
    {
        $this->pipes = $pipes;

        return $this;
    }

    public function thenReturn(): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            fn (Closure $next, object $pipe) => fn (mixed $passable) => $pipe->handle($passable, $next),
            fn (mixed $passable) => $passable,
        );

        return $pipeline($this->passable);
    }
}
