<?php

namespace Concept7\Kite\Http\Integrations\Kite\Requests;

use Concept7\Kite\Http\Integrations\Kite\Dtos\ConfigDto;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class ConfigRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/api/config';
    }

    public function createDtoFromResponse(Response $response): ConfigDto
    {
        $config = $response->json('config', []);

        return new ConfigDto(
            monitoredPackages: $config['monitored_packages'] ?? [],
            isSharingAllPackages: $config['is_sharing_all_packages'] ?? true,
        );
    }
}
