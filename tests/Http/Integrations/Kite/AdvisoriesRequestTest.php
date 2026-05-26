<?php

use Concept7\Kite\Http\Integrations\Kite\KiteConnector;
use Concept7\Kite\Http\Integrations\Kite\Requests\AdvisoriesRequest;
use Concept7\Kite\KiteConfig;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockClient;

test('AdvisoriesRequest resolves correct endpoint', function (): void {
    $request = new AdvisoriesRequest([]);

    expect($request->resolveEndpoint())->toBe('/api/project/advisories');
});

test('AdvisoriesRequest sends advisories as body', function (): void {
    $advisories = [
        ['advisory_id' => 'PKSA-2024-0001', 'package' => 'laravel/framework'],
    ];

    $mockClient = new MockClient([
        AdvisoriesRequest::class => new Fixture('Kite/advisories'),
    ]);

    $connector = new KiteConnector(new KiteConfig(token: 'test-token'));
    $connector->withMockClient($mockClient);

    $connector->send(new AdvisoriesRequest($advisories));

    $mockClient->assertSent(AdvisoriesRequest::class);
});
