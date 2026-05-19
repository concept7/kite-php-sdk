<?php

namespace Concept7\Kite;

class KiteConfig
{
    public const BASE_URL = 'https://kite-monitor.com';

    public function __construct(
        public readonly ?string $token = null,
        public readonly ?string $uri = null,
    ) {}

    public function isValid(): bool
    {
        return filled($this->token);
    }
}
