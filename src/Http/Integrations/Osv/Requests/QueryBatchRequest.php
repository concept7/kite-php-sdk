<?php

namespace Concept7\Kite\Http\Integrations\Osv\Requests;

use Concept7\Kite\Enums\Ecosystem;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class QueryBatchRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param array<array{name: string, version: string}> $packages */
    public function __construct(protected array $packages) {}

    public function resolveEndpoint(): string
    {
        return 'querybatch';
    }

    protected function defaultBody(): array
    {
        return [
            'queries' => array_map(fn (array $package): array => [
                'version' => $package['version'],
                'package' => [
                    'name' => $package['name'],
                    'ecosystem' => Ecosystem::Npm->value,
                ],
            ], $this->packages),
        ];
    }
}
