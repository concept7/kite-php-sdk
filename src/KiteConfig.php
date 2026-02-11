<?php

namespace Concept7\Kite;

class KiteConfig
{
    public function __construct(
        public readonly string $uri,
        public readonly string $projectId,
        public readonly string $projectKey,
        public readonly string $projectRoot,
        public readonly string $phpPath = 'php',
    ) {}

    public function isValid(): bool
    {
        return $this->uri !== ''
            && $this->projectId !== ''
            && $this->projectKey !== '';
    }

    public function apiUrl(): string
    {
        return rtrim($this->uri, '/').'/api/project/'.$this->projectId;
    }
}
