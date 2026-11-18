<?php

namespace Concept7\Kite\Support;

use Composer\Semver\Semver;
use Concept7\Kite\Enums\Ecosystem;
use Concept7\Kite\Enums\Severity;
use Concept7\Kite\Http\Integrations\Packagist\PackagistConnector;
use Concept7\Kite\Http\Integrations\Packagist\Requests\GetSecurityAdvisoriesRequest;

class ComposerAdvisories
{
    /**
     * @param  array<array{name: string, version: string, ecosystem: Ecosystem|string}>  $packages
     * @return array<array{advisory_id: string, package: string, version: string, ecosystem: string, title: string, link: string|null, cve: string|null, severity: string|null, reported_at: string|null, fixed_version?: string|null}>
     */
    public static function scan(array $packages): array
    {
        $composerPackages = array_values(array_filter(
            $packages,
            fn (array $package): bool => Ecosystem::parse(data_get($package, 'ecosystem')) === Ecosystem::Composer,
        ));

        if (blank($composerPackages)) {
            return [];
        }

        $packageNames = array_column($composerPackages, 'name');

        $connector = new PackagistConnector;
        $response = $connector->send(new GetSecurityAdvisoriesRequest($packageNames));

        if ($response->failed()) {
            return [];
        }

        $advisoriesByPackage = $response->dto()->advisoriesByPackage;
        $advisories = [];

        foreach ($composerPackages as $package) {
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
                    $advisoryPayload['fixed_version'] = FixedVersion::fromAffectedVersions(
                        $package['version'],
                        $affectedVersions,
                    );
                }

                $advisories[] = $advisoryPayload;
            }
        }

        return $advisories;
    }
}
