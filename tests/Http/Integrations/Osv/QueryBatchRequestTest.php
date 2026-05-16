<?php

use Concept7\Kite\Http\Integrations\Osv\OsvConnector;
use Concept7\Kite\Http\Integrations\Osv\Requests\QueryBatchRequest;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;

test('QueryBatchRequest resolves correct endpoint', function (): void {
    $request = new QueryBatchRequest([]);

    expect($request->resolveEndpoint())->toBe('querybatch');
});

test('QueryBatchRequest returns results from response', function (): void {
    $packages = [
        ['name' => 'lodash', 'version' => '4.17.15'],
        ['name' => 'axios', 'version' => '0.21.1'],
    ];

    $mockClient = new MockClient([
        QueryBatchRequest::class => new Fixture('Osv/query-batch'),
    ]);

    $connector = new OsvConnector;
    $connector->withMockClient($mockClient);

    $response = $connector->send(new QueryBatchRequest($packages));

    expect($response->json('results'))->toHaveCount(2)
        ->and($response->json('results.0.vulns.0.id'))->toBe('GHSA-1234-abcd-5678');
});
