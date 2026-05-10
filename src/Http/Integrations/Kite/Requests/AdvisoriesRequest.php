<?php

namespace Concept7\Kite\Http\Integrations\Kite\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class AdvisoriesRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private array $advisories,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/project/advisories';
    }

    protected function defaultBody(): array
    {
        return ['advisories' => $this->advisories];
    }
}
