<?php

use Concept7\Kite\Contracts\ProjectInfoCollectorInterface;
use Concept7\Kite\Http\Integrations\Kite\Requests\AdvisoriesRequest;
use Concept7\Kite\Http\Integrations\Kite\Requests\ConfigRequest;
use Concept7\Kite\Http\Integrations\Kite\Requests\ReportRequest;
use Concept7\Kite\Http\Integrations\Osv\Requests\QueryBatchRequest;
use Concept7\Kite\Http\Integrations\Packagist\Requests\GetSecurityAdvisoriesRequest;
use Concept7\Kite\Kite;
use Concept7\Kite\KiteConfig;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

afterEach(fn () => MockClient::destroyGlobal());

function fakeProjectCollector(): ProjectInfoCollectorInterface
{
    return new class implements ProjectInfoCollectorInterface
    {
        public function collect(): array
        {
            return [
                'packages' => [
                    ['name' => 'acme/transitive', 'version' => '1.2.0', 'ecosystem' => 'composer'],
                    ['name' => 'acme/ghost', 'version' => '1.0.0', 'ecosystem' => 'composer'],
                    ['name' => 'left-pad', 'version' => '2.3.1', 'ecosystem' => 'npm'],
                    ['name' => 'ghost-npm', 'version' => '1.0.0', 'ecosystem' => 'npm'],
                ],
            ];
        }
    };
}

function fakeKiteEndpoints(): MockClient
{
    return MockClient::global([
        ConfigRequest::class => MockResponse::make(['config' => ['monitored_packages' => [], 'is_sharing_all_packages' => true]]),
        GetSecurityAdvisoriesRequest::class => MockResponse::make(['advisories' => [
            'acme/transitive' => [[
                'advisoryId' => 'PKSA-1',
                'title' => 'Transitive flaw',
                'severity' => 'high',
                'affectedVersions' => '>=1.0,<1.2.3',
            ]],
            'acme/ghost' => [[
                'advisoryId' => 'PKSA-2',
                'title' => 'Ghost flaw',
                'severity' => 'low',
            ]],
        ]]),
        QueryBatchRequest::class => MockResponse::make(['results' => [
            ['vulns' => [['id' => 'GHSA-LEFT']]],
            ['vulns' => [['id' => 'GHSA-GHOST']]],
        ]]),
        '*/vulns/GHSA-LEFT' => MockResponse::make([
            'id' => 'GHSA-LEFT',
            'summary' => 'Left pad flaw',
            'affected' => [[
                'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
                'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['fixed' => '2.3.2']]]],
            ]],
        ]),
        '*/vulns/GHSA-GHOST' => MockResponse::make(['id' => 'GHSA-GHOST', 'summary' => 'Ghost npm flaw']),
        ReportRequest::class => MockResponse::make(['message' => 'ok']),
        AdvisoriesRequest::class => MockResponse::make(['message' => 'ok']),
    ]);
}

function advisoriesByPackage(array $advisories): array
{
    return collect($advisories)->keyBy('package')->all();
}

function expectFixtureAdvisories(array $advisories): void
{
    $byPackage = advisoriesByPackage($advisories);

    expect($byPackage)->toHaveCount(4)
        ->and($byPackage['acme/transitive']['fixed_version'])->toBe('1.2.3')
        ->and($byPackage['acme/transitive']['dependency_paths'])->toBe([
            [
                ['name' => 'acme/direct', 'version' => '1.0.0', 'constraint' => '^1.0'],
                ['name' => 'acme/transitive', 'version' => '1.2.0', 'constraint' => '^1.2'],
            ],
        ])
        ->and($byPackage['left-pad']['fixed_version'])->toBe('2.3.2')
        ->and($byPackage['left-pad']['dependency_paths'])->toBe([
            [
                ['name' => 'direct-npm', 'version' => '1.0.0', 'constraint' => '^1.0.0'],
                ['name' => 'left-pad', 'version' => '2.3.1', 'constraint' => '^2.0.0'],
            ],
        ])
        ->and($byPackage['acme/ghost'])->not->toHaveKeys(['fixed_version', 'dependency_paths'])
        ->and($byPackage['ghost-npm'])->not->toHaveKeys(['fixed_version', 'dependency_paths']);
}

test('report sends fixed versions and dependency paths', function (): void {
    $mockClient = fakeKiteEndpoints();

    Kite::make(new KiteConfig(token: 'test-token'))
        ->setActions([])
        ->projectInfoCollector(fakeProjectCollector())
        ->basePath(__DIR__.'/Fixtures/Project')
        ->report();

    $request = $mockClient->getLastRequest();

    expect($request)->toBeInstanceOf(ReportRequest::class);

    expectFixtureAdvisories($request->body()->all()['advisories']);
});

test('checkAdvisories sends fixed versions and dependency paths', function (): void {
    $mockClient = fakeKiteEndpoints();

    Kite::make(new KiteConfig(token: 'test-token'))
        ->projectInfoCollector(fakeProjectCollector())
        ->basePath(__DIR__.'/Fixtures/Project')
        ->checkAdvisories();

    $request = $mockClient->getLastRequest();

    expect($request)->toBeInstanceOf(AdvisoriesRequest::class);

    expectFixtureAdvisories($request->body()->all()['advisories']);
});
