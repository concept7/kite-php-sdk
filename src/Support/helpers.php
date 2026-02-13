<?php

use Concept7\Kite\Support\Collection;

if (! function_exists('collect')) {
    function collect(array $items = []): Collection
    {
        return new Collection($items);
    }
}

if (! function_exists('filled')) {
    function filled(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }
}

if (! function_exists('blank')) {
    function blank(mixed $value): bool
    {
        return ! filled($value);
    }
}

if (! function_exists('data_get')) {
    function data_get(mixed $target, string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } else {
                return $default;
            }
        }

        return $target;
    }
}
