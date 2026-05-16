<?php

use Concept7\Kite\Http\Integrations\Kite\Dtos\ConfigDto;
use Concept7\Kite\Http\Integrations\Kite\KiteConnector;
use Concept7\Kite\Http\Integrations\Kite\Requests\ConfigRequest;
use Concept7\Kite\KiteConfig;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;

test('ConfigRequest resolves correct endpoint', function (): void {
    $request = new ConfigRequest;

    expect($request->resolveEndpoint())->toBe('/api/config');
});

test('ConfigRequest maps response to ConfigDto', function (): void {
    $mockClient = new MockClient([
        ConfigRequest::class => new Fixture('Kite/config'),
    ]);

    $connector = new KiteConnector(new KiteConfig(token: 'test-token'));
    $connector->withMockClient($mockClient);

    $response = $connector->send(new ConfigRequest);
    $dto = $response->dtoOrFail();

    expect($dto)->toBeInstanceOf(ConfigDto::class)
        ->and($dto->monitoredPackages)->toBe(['laravel/framework', 'spatie/laravel-permission'])
        ->and($dto->isSharingAllPackages)->toBeFalse();
});
