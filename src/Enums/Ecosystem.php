<?php

namespace Concept7\Kite\Enums;

enum Ecosystem: string
{
    case Composer = 'composer';
    case Npm = 'npm';
    case Wordpress = 'wordpress';

    public static function parse(null|int|string|self $value): ?static
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom($value);
    }
}
