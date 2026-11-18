<?php

use Concept7\Kite\Http\Integrations\Packagist\Requests\GetSecurityAdvisoriesRequest;
use Concept7\Kite\Support\ComposerAdvisories;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

afterEach(fn () => MockClient::destroyGlobal());

function fakePackagistAdvisory(array $advisory): void
{
    MockClient::global([
        GetSecurityAdvisoriesRequest::class => MockResponse::make([
            'advisories' => [
                'laravel/framework' => [
                    array_merge([
                        'advisoryId' => 'PKSA-2024-0001',
                        'packageName' => 'laravel/framework',
                        'title' => 'Remote code execution vulnerability',
                        'severity' => 'high',
                    ], $advisory),
                ],
            ],
        ]),
    ]);
}

$laravelPackage = [['name' => 'laravel/framework', 'version' => '1.2.0', 'ecosystem' => 'composer']];

test('composer advisory carries the fixed version', function () use ($laravelPackage): void {
    fakePackagistAdvisory(['affectedVersions' => '>=1.0,<1.2.3']);

    $advisories = ComposerAdvisories::scan($laravelPackage);

    expect($advisories)->toHaveCount(1)
        ->and($advisories[0]['fixed_version'])->toBe('1.2.3');
});

test('composer advisory with an open range has a null fixed version', function () use ($laravelPackage): void {
    fakePackagistAdvisory(['affectedVersions' => '>=1.0']);

    $advisories = ComposerAdvisories::scan($laravelPackage);

    expect(array_key_exists('fixed_version', $advisories[0]))->toBeTrue()
        ->and($advisories[0]['fixed_version'])->toBeNull();
});

test('composer advisory without affected versions has no fixed version key', function () use ($laravelPackage): void {
    fakePackagistAdvisory([]);

    $advisories = ComposerAdvisories::scan($laravelPackage);

    expect($advisories)->toHaveCount(1)
        ->and(array_key_exists('fixed_version', $advisories[0]))->toBeFalse();
});
