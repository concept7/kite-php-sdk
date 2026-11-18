<?php

namespace Concept7\Kite\Support;

use Concept7\Kite\Enums\Ecosystem;
use Concept7\Kite\Enums\Severity;
use Concept7\Kite\Exceptions\AdvisorySourceUnavailable;
use Concept7\Kite\Http\Integrations\Osv\OsvConnector;
use Concept7\Kite\Http\Integrations\Osv\Requests\GetVulnerabilityRequest;
use Concept7\Kite\Http\Integrations\Osv\Requests\QueryBatchRequest;
use Throwable;

class NpmAdvisories
{
    /**
     * @param  array<array{name: string, version: string, ecosystem: Ecosystem|string}>  $packages
     * @return array<array{advisory_id: string, package: string, version: string, ecosystem: string, title: string, link: string|null, cve: string|null, severity: string|null, reported_at: string|null, fixed_version?: string|null, dependency_paths?: list<list<array{name: string, version: string, constraint: string}>>}>
     */
    public static function scan(array $packages, ?string $basePath = null): array
    {
        $npmPackages = array_values(array_filter(
            $packages,
            fn (array $package): bool => Ecosystem::parse(data_get($package, 'ecosystem')) === Ecosystem::Npm,
        ));

        if (blank($npmPackages)) {
            return [];
        }

        $connector = new OsvConnector;
        $response = $connector->send(new QueryBatchRequest($npmPackages));

        if ($response->failed()) {
            throw AdvisorySourceUnavailable::for('OSV');
        }

        $results = $response->dto()->results;

        $vulnIdToIndexes = [];
        foreach ($results as $index => $result) {
            foreach (data_get($result, 'vulns', []) as $vuln) {
                $vulnIdToIndexes[$vuln['id']][] = $index;
            }
        }

        if (blank($vulnIdToIndexes)) {
            return [];
        }

        $basePath ??= DependencyPaths::basePath();
        $dependencyPathsByPackage = [];
        $advisories = [];

        foreach ($vulnIdToIndexes as $vulnId => $packageIndexes) {
            $detailResponse = $connector->send(new GetVulnerabilityRequest($vulnId));

            if ($detailResponse->failed()) {
                throw AdvisorySourceUnavailable::for('OSV');
            }

            $vulnerability = $detailResponse->dto()->data;
            $normalized = static::normalizeVuln($vulnerability);

            foreach (array_unique($packageIndexes) as $packageIndex) {
                $package = $npmPackages[$packageIndex];

                $advisory = array_merge($normalized, [
                    'package' => $package['name'],
                    'version' => $package['version'],
                    'ecosystem' => Ecosystem::Npm->value,
                ]);

                $fixedVersion = FixedVersion::forOsv(
                    $package['version'],
                    $vulnerability,
                    $package['name'],
                );

                if ($fixedVersion['known']) {
                    $advisory['fixed_version'] = $fixedVersion['version'];
                }

                $cacheKey = $package['name'].'@'.$package['version'];

                if (! array_key_exists($cacheKey, $dependencyPathsByPackage)) {
                    try {
                        $dependencyPathsByPackage[$cacheKey] = DependencyPaths::npm($basePath, $package['name'], $package['version']);
                    } catch (Throwable) {
                        $dependencyPathsByPackage[$cacheKey] = null;
                    }
                }

                if (filled($dependencyPathsByPackage[$cacheKey])) {
                    $advisory['dependency_paths'] = $dependencyPathsByPackage[$cacheKey];
                }

                $advisories[] = $advisory;
            }
        }

        return $advisories;
    }

    /** @return array{advisory_id: string, title: string, link: string|null, cve: string|null, severity: string|null, reported_at: string|null} */
    private static function normalizeVuln(array $vuln): array
    {
        $link = null;
        foreach (data_get($vuln, 'references', []) as $reference) {
            if ($reference['type'] === 'ADVISORY') {
                $link = $reference['url'];
                break;
            }
        }
        $link ??= data_get($vuln, 'references.0.url');

        $cve = null;
        foreach (data_get($vuln, 'aliases', []) as $alias) {
            if (str_starts_with($alias, 'CVE-')) {
                $cve = $alias;
                break;
            }
        }

        $severity = Severity::parse(data_get($vuln, 'database_specific.severity'))?->value;

        return [
            'advisory_id' => $vuln['id'],
            'title' => data_get($vuln, 'summary', ''),
            'link' => $link,
            'cve' => $cve,
            'severity' => $severity,
            'reported_at' => transform(
                data_get($vuln, 'published'),
                fn (string $published): string => date('Y-m-d', strtotime($published)),
            ),
        ];
    }
}
