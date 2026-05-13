<?php

namespace Concept7\Kite\Http\Integrations\Kite\Dtos;

class ConfigDto
{
    public function __construct(
        public readonly array $monitoredPackages,
        public readonly bool $isSharingAllPackages,
    ) {}
}
