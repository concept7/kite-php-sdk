<?php

namespace Concept7\Kite\Http\Integrations\Packagist\Requests;

use Concept7\Kite\Http\Integrations\Packagist\Dtos\SecurityAdvisoriesDto;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
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

    public function createDtoFromResponse(Response $response): SecurityAdvisoriesDto
    {
        return new SecurityAdvisoriesDto(
            advisoriesByPackage: $response->json('advisories') ?? [],
        );
    }
}
