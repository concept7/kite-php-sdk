<?php

use Concept7\Kite\Exceptions\AdvisorySourceUnavailable;
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

test('npm advisory with only a last affected event has no fixed version key', function () use ($leftPadPackage): void {
    fakeOsvVulnerability([[
        'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
        'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['last_affected' => '2.3.1']]]],
    ]]);

    $advisories = NpmAdvisories::scan($leftPadPackage);

    expect(array_key_exists('fixed_version', $advisories[0]))->toBeFalse();
});

test('npm advisory with an open range has a null fixed version', function () use ($leftPadPackage): void {
    fakeOsvVulnerability([[
        'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
        'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0']]]],
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

$leftPadRanges = [[
    'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
    'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['fixed' => '2.3.2']]]],
]];

test('npm advisory for a transitive package carries dependency paths', function () use ($leftPadPackage, $leftPadRanges): void {
    fakeOsvVulnerability($leftPadRanges);

    $advisories = NpmAdvisories::scan($leftPadPackage, __DIR__.'/../Fixtures/Project');

    expect($advisories[0]['dependency_paths'])->toBe([
        [
            ['name' => 'direct-npm', 'version' => '1.0.0', 'constraint' => '^1.0.0'],
            ['name' => 'left-pad', 'version' => '2.3.1', 'constraint' => '^2.0.0'],
        ],
    ]);
});

test('npm advisory has no dependency paths key when none are found', function (string $basePath) use ($leftPadPackage, $leftPadRanges): void {
    fakeOsvVulnerability($leftPadRanges);

    $advisories = NpmAdvisories::scan($leftPadPackage, $basePath);

    expect($advisories)->toHaveCount(1)
        ->and(array_key_exists('dependency_paths', $advisories[0]))->toBeFalse();
})->with([
    'no project files' => [sys_get_temp_dir()],
    'package not in lockfile' => [__DIR__.'/../Fixtures/Npm/cycle'],
]);

test('npm paths are computed once per package and version', function () use ($leftPadRanges): void {
    MockClient::global([
        QueryBatchRequest::class => MockResponse::make(['results' => [['vulns' => [['id' => 'GHSA-1'], ['id' => 'GHSA-2']]]]]),
        GetVulnerabilityRequest::class => MockResponse::make(['id' => 'GHSA-1', 'summary' => 'x', 'affected' => $leftPadRanges]),
    ]);

    $advisories = NpmAdvisories::scan(
        [['name' => 'left-pad', 'version' => '2.3.1', 'ecosystem' => 'npm']],
        __DIR__.'/../Fixtures/Project',
    );

    expect($advisories)->toHaveCount(2)
        ->and($advisories[1]['dependency_paths'])->toBe($advisories[0]['dependency_paths']);
});

test('a failure while building npm paths drops only the paths', function () use ($leftPadPackage, $leftPadRanges): void {
    fakeOsvVulnerability($leftPadRanges);

    $directory = sys_get_temp_dir().'/kite-unreadable-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/package.json', '{}');
    file_put_contents($directory.'/package-lock.json', '{}');
    chmod($directory.'/package-lock.json', 0000);

    set_error_handler(function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        $advisories = NpmAdvisories::scan($leftPadPackage, $directory);
    } finally {
        restore_error_handler();
        chmod($directory.'/package-lock.json', 0644);
        array_map('unlink', glob($directory.'/*'));
        rmdir($directory);
    }

    expect($advisories)->toHaveCount(1)
        ->and($advisories[0]['advisory_id'])->toBe('GHSA-1234-abcd-5678')
        ->and(array_key_exists('dependency_paths', $advisories[0]))->toBeFalse();
})->skip(function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can read unreadable files');

test('npm scan throws when the osv batch query fails', function () use ($leftPadPackage): void {
    MockClient::global([
        QueryBatchRequest::class => MockResponse::make([], 500),
    ]);

    NpmAdvisories::scan($leftPadPackage);
})->throws(AdvisorySourceUnavailable::class);

test('npm scan throws when one vulnerability lookup fails', function () use ($leftPadPackage): void {
    MockClient::global([
        QueryBatchRequest::class => MockResponse::make(['results' => [['vulns' => [['id' => 'GHSA-1'], ['id' => 'GHSA-2']]]]]),
        '*/vulns/GHSA-1' => MockResponse::make(['id' => 'GHSA-1', 'summary' => 'First']),
        '*/vulns/GHSA-2' => MockResponse::make([], 500),
    ]);

    NpmAdvisories::scan($leftPadPackage);
})->throws(AdvisorySourceUnavailable::class);
