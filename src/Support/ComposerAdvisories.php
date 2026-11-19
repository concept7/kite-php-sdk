<?php

namespace Concept7\Kite\Support;

use Composer\Semver\Semver;
use Concept7\Kite\Enums\Ecosystem;
use Concept7\Kite\Enums\Severity;
use Concept7\Kite\Exceptions\AdvisorySourceUnavailable;
use Concept7\Kite\Http\Integrations\Packagist\PackagistConnector;
use Concept7\Kite\Http\Integrations\Packagist\Requests\GetSecurityAdvisoriesRequest;
use Throwable;

class ComposerAdvisories
{
    /**
     * @param  array<array{name: string, version: string, ecosystem: Ecosystem|string}>  $packages
     * @return array<array{advisory_id: string, package: string, version: string, ecosystem: string, title: string, link: string|null, cve: string|null, severity: string|null, reported_at: string|null, fixed_version?: string|null, dependency_paths?: list<list<array{name: string, version: string, constraint: string}>>}>
     */
    public static function scan(array $packages, ?string $basePath = null): array
    {
        $composerPackages = array_values(array_filter(
            $packages,
            fn (array $package): bool => data_get($package, 'ecosystem') === Ecosystem::Composer,
        ));

        if (blank($composerPackages)) {
            return [];
        }

        $packageNames = array_column($composerPackages, 'name');

        $connector = new PackagistConnector;
        $response = $connector->send(new GetSecurityAdvisoriesRequest($packageNames));

        if ($response->failed()) {
            throw AdvisorySourceUnavailable::for('Packagist');
        }

        $advisoriesByPackage = $response->dto()->advisoriesByPackage;
        $advisories = [];

        $basePath ??= DependencyPaths::basePath();

        foreach ($composerPackages as $package) {
            $packageAdvisories = [];

            foreach (data_get($advisoriesByPackage, $package['name'], []) as $advisory) {
                $affectedVersions = data_get($advisory, 'affectedVersions');

                if ($affectedVersions && ! Semver::satisfies($package['version'], $affectedVersions)) {
                    continue;
                }

                $advisoryPayload = [
                    'advisory_id' => $advisory['advisoryId'],
                    'package' => $package['name'],
                    'version' => $package['version'],
                    'ecosystem' => Ecosystem::Composer->value,
                    'title' => data_get($advisory, 'title', ''),
                    'link' => data_get($advisory, 'link'),
                    'cve' => data_get($advisory, 'cve'),
                    'severity' => Severity::parse(data_get($advisory, 'severity'))?->value,
                    'reported_at' => transform(
                        data_get($advisory, 'reportedAt'),
                        fn (string $reportedAt): string => date('Y-m-d', strtotime($reportedAt)),
                    ),
                ];

                if (filled($affectedVersions)) {
                    $fixedVersion = FixedVersion::forPackagist($package['version'], $affectedVersions);

                    if ($fixedVersion['known']) {
                        $advisoryPayload['fixed_version'] = $fixedVersion['version'];
                    }
                }

                $packageAdvisories[] = $advisoryPayload;
            }

            if (blank($packageAdvisories)) {
                continue;
            }

            try {
                $dependencyPaths = DependencyPaths::composer($basePath, $package['name']);
            } catch (Throwable) {
                $dependencyPaths = null;
            }

            foreach ($packageAdvisories as $packageAdvisory) {
                if (filled($dependencyPaths)) {
                    $packageAdvisory['dependency_paths'] = $dependencyPaths;
                }

                $advisories[] = $packageAdvisory;
            }
        }

        return $advisories;
    }
}
