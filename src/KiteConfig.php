<?php

namespace Concept7\Kite;

class KiteConfig
{
    public function __construct(
        public readonly ?string $uri = null,
        public readonly ?string $projectId = null,
        public readonly ?string $projectKey = null,
    ) {}

    public function isValid(): bool
    {
        return filled($this->uri)
            && filled($this->projectId)
            && filled($this->projectKey);
    }

}
