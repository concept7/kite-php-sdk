<?php

namespace Concept7\Kite\Support;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use UnexpectedValueException;

class FixedVersion
{
    /**
     * @return array{known: bool, version: string|null}
     */
    public static function forPackagist(string $installedVersion, string $affectedVersions): array
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
                return ['known' => true, 'version' => $matches[1]];
            }

            return [
                'known' => ! str_contains($affectedRange, '<='),
                'version' => null,
            ];
        }

        return ['known' => false, 'version' => null];
    }

    /**
     * @param  array{affected?: list<array{package?: array{name?: string, ecosystem?: string}, ranges?: list<array{type?: string, events?: list<array<string, string>>}>}>}  $vulnerability
     * @return array{known: bool, version: string|null}
     */
    public static function forOsv(string $installedVersion, array $vulnerability, string $package): array
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
                $isOpen = false;

                foreach (data_get($range, 'events', []) as $event) {
                    $fixed = data_get($event, 'fixed');
                    $lastAffected = data_get($event, 'last_affected');

                    if (filled(data_get($event, 'introduced'))) {
                        $introduced = $event['introduced'];
                        $isOpen = true;

                        continue;
                    }

                    $isOpen = false;

                    try {
                        if (filled($fixed) && Comparator::greaterThanOrEqualTo($installedVersion, $introduced) && Comparator::lessThan($installedVersion, $fixed)) {
                            return ['known' => true, 'version' => $fixed];
                        }

                        if (filled($lastAffected) && Comparator::greaterThanOrEqualTo($installedVersion, $introduced) && Comparator::lessThanOrEqualTo($installedVersion, $lastAffected)) {
                            return ['known' => false, 'version' => null];
                        }
                    } catch (UnexpectedValueException) {
                        continue;
                    }
                }

                try {
                    if ($isOpen && Comparator::greaterThanOrEqualTo($installedVersion, $introduced)) {
                        return ['known' => true, 'version' => null];
                    }
                } catch (UnexpectedValueException) {
                    continue;
                }
            }
        }

        return ['known' => false, 'version' => null];
    }
}
