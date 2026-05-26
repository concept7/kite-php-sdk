<?php

namespace Concept7\Kite\Enums;

enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public static function parse(null|string|self $value): ?static
    {
        if ($value instanceof self) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'critical' => self::Critical,
            'high' => self::High,
            'moderate', 'medium' => self::Medium,
            'low' => self::Low,
            default => null,
        };
    }
}
