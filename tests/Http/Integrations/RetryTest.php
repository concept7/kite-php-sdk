<?php

use Concept7\Kite\Enums\Ecosystem;
use Concept7\Kite\Exceptions\AdvisorySourceUnavailable;
use Concept7\Kite\Http\Integrations\Osv\OsvConnector;
use Concept7\Kite\Http\Integrations\Packagist\PackagistConnector;
use Concept7\Kite\Support\ComposerAdvisories;
use Concept7\Kite\Support\NpmAdvisories;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

afterEach(fn () => MockClient::destroyGlobal());

$composerPackage = [['name' => 'laravel/framework', 'version' => '1.2.0', 'ecosystem' => Ecosystem::Composer]];
$npmPackage = [['name' => 'left-pad', 'version' => '2.3.1', 'ecosystem' => Ecosystem::Npm]];

$packagistSuccess = fn (): MockResponse => MockResponse::make(['advisories' => [
    'laravel/framework' => [['advisoryId' => 'PKSA-1', 'title' => 'Flaw', 'affectedVersions' => '>=1.0,<1.2.3']],
]]);

test('connectors retry three times with exponential backoff', function (string $connectorClass): void {
    $connector = new $connectorClass;

    expect($connector->tries)->toBe(3)
        ->and($connector->useExponentialBackoff)->toBeTrue()
        ->and($connector->throwOnMaxTries)->toBeFalse();
})->with([
    'packagist' => [PackagistConnector::class],
    'osv' => [OsvConnector::class],
]);

test('packagist retries a server error and then succeeds', function () use ($composerPackage, $packagistSuccess): void {
    $mockClient = MockClient::global([MockResponse::make([], 503), $packagistSuccess()]);

    expect(ComposerAdvisories::scan($composerPackage))->toHaveCount(1);

    $mockClient->assertSentCount(2);
});

test('packagist throws after three server errors', function () use ($composerPackage): void {
    $mockClient = MockClient::global([
        MockResponse::make([], 503),
        MockResponse::make([], 503),
        MockResponse::make([], 503),
    ]);

    expect(fn () => ComposerAdvisories::scan($composerPackage))->toThrow(AdvisorySourceUnavailable::class);

    $mockClient->assertSentCount(3);
});

test('packagist does not retry a client error', function () use ($composerPackage): void {
    $mockClient = MockClient::global([MockResponse::make([], 404), MockResponse::make([], 200)]);

    expect(fn () => ComposerAdvisories::scan($composerPackage))->toThrow(AdvisorySourceUnavailable::class);

    $mockClient->assertSentCount(1);
});

test('osv retries a server error and then succeeds', function () use ($npmPackage): void {
    $mockClient = MockClient::global([
        MockResponse::make([], 502),
        MockResponse::make(['results' => [[]]]),
    ]);

    expect(NpmAdvisories::scan($npmPackage))->toBe([]);

    $mockClient->assertSentCount(2);
});

test('osv does not retry a client error', function () use ($npmPackage): void {
    $mockClient = MockClient::global([MockResponse::make([], 400), MockResponse::make([], 200)]);

    expect(fn () => NpmAdvisories::scan($npmPackage))->toThrow(AdvisorySourceUnavailable::class);

    $mockClient->assertSentCount(1);
});
