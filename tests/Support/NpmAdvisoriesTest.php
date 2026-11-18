<?php

use Concept7\Kite\Http\Integrations\Osv\Requests\GetVulnerabilityRequest;
use Concept7\Kite\Http\Integrations\Osv\Requests\QueryBatchRequest;
use Concept7\Kite\Support\NpmAdvisories;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

afterEach(fn () => MockClient::destroyGlobal());

function fakeOsvVulnerability(array $affected): void
{
    MockClient::global([
        QueryBatchRequest::class => MockResponse::make(['results' => [['vulns' => [['id' => 'GHSA-1234-abcd-5678']]]]]),
        GetVulnerabilityRequest::class => MockResponse::make([
            'id' => 'GHSA-1234-abcd-5678',
            'summary' => 'Prototype pollution',
            'affected' => $affected,
        ]),
    ]);
}

$leftPadPackage = [['name' => 'left-pad', 'version' => '2.3.1', 'ecosystem' => 'npm']];

test('npm advisory carries the fixed version', function () use ($leftPadPackage): void {
    fakeOsvVulnerability([[
        'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
        'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['fixed' => '2.3.2']]]],
    ]]);

    $advisories = NpmAdvisories::scan($leftPadPackage);

    expect($advisories)->toHaveCount(1)
        ->and($advisories[0]['fixed_version'])->toBe('2.3.2');
});

test('npm advisory without a fixed event has a null fixed version', function () use ($leftPadPackage): void {
    fakeOsvVulnerability([[
        'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
        'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['last_affected' => '2.3.1']]]],
    ]]);

    $advisories = NpmAdvisories::scan($leftPadPackage);

    expect(array_key_exists('fixed_version', $advisories[0]))->toBeTrue()
        ->and($advisories[0]['fixed_version'])->toBeNull();
});

test('npm advisory without ranges has no fixed version key', function () use ($leftPadPackage): void {
    fakeOsvVulnerability([]);

    $advisories = NpmAdvisories::scan($leftPadPackage);

    expect($advisories)->toHaveCount(1)
        ->and(array_key_exists('fixed_version', $advisories[0]))->toBeFalse();
});
