<?php

use Concept7\Kite\Http\Integrations\Packagist\Dtos\SecurityAdvisoriesDto;
use Concept7\Kite\Http\Integrations\Packagist\PackagistConnector;
use Concept7\Kite\Http\Integrations\Packagist\Requests\GetSecurityAdvisoriesRequest;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;

test('GetSecurityAdvisoriesRequest resolves correct endpoint', function (): void {
    $request = new GetSecurityAdvisoriesRequest([]);

    expect($request->resolveEndpoint())->toBe('/api/security-advisories/');
});

test('GetSecurityAdvisoriesRequest returns advisories by package', function (): void {
    $mockClient = new MockClient([
        GetSecurityAdvisoriesRequest::class => new Fixture('Packagist/get-security-advisories'),
    ]);

    $connector = new PackagistConnector;
    $connector->withMockClient($mockClient);

    $response = $connector->send(new GetSecurityAdvisoriesRequest(['laravel/framework']));
    $advisories = $response->json('advisories');

    expect($advisories)->toHaveKey('laravel/framework')
        ->and($advisories['laravel/framework'][0]['advisoryId'])->toBe('PKSA-2024-0001')
        ->and($advisories['laravel/framework'][0]['severity'])->toBe('high');
});

test('GetSecurityAdvisoriesRequest maps response to SecurityAdvisoriesDto', function (): void {
    $mockClient = new MockClient([
        GetSecurityAdvisoriesRequest::class => new Fixture('Packagist/get-security-advisories'),
    ]);

    $connector = new PackagistConnector;
    $connector->withMockClient($mockClient);

    $dto = $connector->send(new GetSecurityAdvisoriesRequest(['laravel/framework']))->dtoOrFail();

    expect($dto)->toBeInstanceOf(SecurityAdvisoriesDto::class)
        ->and($dto->advisoriesByPackage)->toHaveKey('laravel/framework')
        ->and($dto->advisoriesByPackage['laravel/framework'][0]['advisoryId'])->toBe('PKSA-2024-0001');
});
