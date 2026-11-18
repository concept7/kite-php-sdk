<?php

namespace Concept7\Kite\Enums;

enum Ecosystem: string
{
    case Composer = 'composer';
    case Npm = 'npm';
    case Wordpress = 'wordpress';

    public static function parse(null|string|self $value): ?static
    {
        if ($value instanceof self) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        return self::tryFrom($value);
    }
}
