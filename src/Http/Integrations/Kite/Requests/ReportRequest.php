<?php

namespace Concept7\Kite\Http\Integrations\Kite\Requests;

use Concept7\Kite\Http\Integrations\Kite\Dtos\ProjectReportDto;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class ReportRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private string $projectId,
        private array $payload,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/project/'.$this->projectId;
    }

    protected function defaultBody(): array
    {
        return $this->payload;
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        $data = $response->json();

        return new ProjectReportDto(
            message: $data['message'],
        );
    }
}
