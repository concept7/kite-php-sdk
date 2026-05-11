<?php

namespace Concept7\Kite\Support;

use Concept7\Kite\Enums\Ecosystem;
use Concept7\Kite\Http\Integrations\Osv\OsvConnector;
use Concept7\Kite\Http\Integrations\Osv\Requests\GetVulnerabilityRequest;
use Concept7\Kite\Http\Integrations\Osv\Requests\QueryBatchRequest;

class NpmAdvisories
{
    /**
     * @param  array<array{name: string, version: string, ecosystem: Ecosystem|string}>  $packages
     * @return array<array{advisory_id: string, package: string, version: string, ecosystem: string, title: string, link: string|null, cve: string|null, severity: string|null, reported_at: string|null}>
     */
    public static function scan(array $packages): array
    {
        $npmPackages = array_values(array_filter(
            $packages,
            fn (array $package): bool => static::isNpm($package['ecosystem'] ?? null),
        ));

        if (blank($npmPackages)) {
            return [];
        }

        $connector = new OsvConnector;
        $response = $connector->send(new QueryBatchRequest($npmPackages));

        if ($response->failed()) {
            return [];
        }

        $results = $response->json('results') ?? [];

        $vulnIdToIndexes = [];
        foreach ($results as $index => $result) {
            foreach (data_get($result, 'vulns', []) as $vuln) {
                $vulnIdToIndexes[$vuln['id']][] = $index;
            }
        }

        if (blank($vulnIdToIndexes)) {
            return [];
        }

        $advisories = [];

        foreach ($vulnIdToIndexes as $vulnId => $packageIndexes) {
            $detailResponse = $connector->send(new GetVulnerabilityRequest($vulnId));

            if ($detailResponse->failed()) {
                continue;
            }

            $normalized = static::normalizeVuln($detailResponse->json());

            foreach (array_unique($packageIndexes) as $packageIndex) {
                $package = $npmPackages[$packageIndex];

                $advisories[] = array_merge($normalized, [
                    'package' => $package['name'],
                    'version' => $package['version'],
                    'ecosystem' => Ecosystem::Npm->value,
                ]);
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

        $severity = match (strtolower(data_get($vuln, 'database_specific.severity', ''))) {
            'critical' => 'critical',
            'high' => 'high',
            'moderate', 'medium' => 'medium',
            'low' => 'low',
            default => null,
        };

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

    private static function isNpm(mixed $ecosystem): bool
    {
        if ($ecosystem instanceof Ecosystem) {
            return $ecosystem === Ecosystem::Npm;
        }

        return $ecosystem === Ecosystem::Npm->value;
    }
}
