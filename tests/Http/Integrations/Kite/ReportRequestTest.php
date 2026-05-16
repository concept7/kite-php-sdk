<?php

use Concept7\Kite\Http\Integrations\Kite\Dtos\ProjectReportDto;
use Concept7\Kite\Http\Integrations\Kite\KiteConnector;
use Concept7\Kite\Http\Integrations\Kite\Requests\ReportRequest;
use Concept7\Kite\KiteConfig;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;

test('ReportRequest resolves correct endpoint', function (): void {
    $request = new ReportRequest(['meta' => []]);

    expect($request->resolveEndpoint())->toBe('/api/project');
});

test('ReportRequest maps response to ProjectReportDto', function (): void {
    $mockClient = new MockClient([
        ReportRequest::class => new Fixture('Kite/report'),
    ]);

    $connector = new KiteConnector(new KiteConfig(token: 'test-token'));
    $connector->withMockClient($mockClient);

    $response = $connector->send(new ReportRequest(['meta' => []]));
    $dto = $response->dtoOrFail();

    expect($dto)->toBeInstanceOf(ProjectReportDto::class)
        ->and($dto->message)->toBe('Project report received');
});
