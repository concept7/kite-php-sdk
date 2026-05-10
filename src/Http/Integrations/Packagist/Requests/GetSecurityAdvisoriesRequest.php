<?php

namespace Concept7\Kite\Http\Integrations\Packagist\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

class GetSecurityAdvisoriesRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    /** @param array<string> $packages */
    public function __construct(protected array $packages) {}

    public function resolveEndpoint(): string
    {
        return '/api/security-advisories/';
    }

    protected function defaultBody(): array
    {
        return ['packages' => $this->packages];
    }
}
