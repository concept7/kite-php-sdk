<?php

namespace Concept7\Kite\Http\Integrations\KiteConnector\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SendReportRequest extends Request implements HasBody
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
}
