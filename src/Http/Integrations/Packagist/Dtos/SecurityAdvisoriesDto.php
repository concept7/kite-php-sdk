<?php

namespace Concept7\Kite\Http\Integrations\Packagist\Dtos;

class SecurityAdvisoriesDto
{
    public function __construct(
        public readonly array $advisoriesByPackage,
    ) {}
}
