<?php

namespace Concept7\Kite\Support;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use UnexpectedValueException;

class FixedVersion
{
    public static function fromAffectedVersions(string $installedVersion, string $affectedVersions): ?string
    {
        foreach (explode('|', $affectedVersions) as $affectedRange) {
            try {
                $containsInstalledVersion = Semver::satisfies(
                    $installedVersion,
                    trim($affectedRange),
                );
            } catch (UnexpectedValueException) {
                continue;
            }

            if (! $containsInstalledVersion) {
                continue;
            }

            if (preg_match('/<(?!=)\s*([^,\s]+)/', $affectedRange, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param  array{affected?: list<array{package?: array{name?: string, ecosystem?: string}, ranges?: list<array{type?: string, events?: list<array<string, string>>}>}>}  $vulnerability
     */
    public static function fromOsv(string $installedVersion, array $vulnerability, string $package): ?string
    {
        foreach (data_get($vulnerability, 'affected', []) as $affected) {
            if (data_get($affected, 'package.name') !== $package || data_get($affected, 'package.ecosystem') !== 'npm') {
                continue;
            }

            foreach (data_get($affected, 'ranges', []) as $range) {
                if (! in_array(data_get($range, 'type'), ['SEMVER', 'ECOSYSTEM'], true)) {
                    continue;
                }

                $introduced = '0';

                foreach (data_get($range, 'events', []) as $event) {
                    $introduced = data_get($event, 'introduced', $introduced);
                    $fixed = data_get($event, 'fixed');

                    if (blank($fixed)) {
                        continue;
                    }

                    try {
                        $isAffectedByRange = Comparator::greaterThanOrEqualTo($installedVersion, $introduced)
                            && Comparator::lessThan($installedVersion, $fixed);
                    } catch (UnexpectedValueException) {
                        continue;
                    }

                    if ($isAffectedByRange) {
                        return $fixed;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array{affected?: list<array{package?: array{name?: string, ecosystem?: string}, ranges?: list<array{type?: string}>}>}  $vulnerability
     */
    public static function hasOsvRanges(array $vulnerability, string $package): bool
    {
        foreach (data_get($vulnerability, 'affected', []) as $affected) {
            if (data_get($affected, 'package.name') !== $package || data_get($affected, 'package.ecosystem') !== 'npm') {
                continue;
            }

            foreach (data_get($affected, 'ranges', []) as $range) {
                if (in_array(data_get($range, 'type'), ['SEMVER', 'ECOSYSTEM'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
