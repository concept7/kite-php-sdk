<?php

namespace Concept7\Kite\Exceptions;

use RuntimeException;

class AdvisorySourceUnavailable extends RuntimeException
{
    public static function for(string $source): self
    {
        return new self("The {$source} advisory source is unavailable.");
    }
}
