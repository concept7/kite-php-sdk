<?php

namespace Concept7\Kite\Http\Integrations\Osv\Dtos;

class QueryBatchResultDto
{
    public function __construct(
        public readonly array $results,
    ) {}
}
