<?php

use Concept7\Kite\Support\FixedVersion;

test('fixed version from packagist affected versions', function (string $installedVersion, string $affectedVersions, ?string $expected): void {
    expect(FixedVersion::fromAffectedVersions($installedVersion, $affectedVersions))->toBe($expected);
})->with([
    'single range' => ['1.2.0', '>=1.0,<1.2.3', '1.2.3'],
    'second range' => ['2.0.5', '>=1.0,<1.2.3|>=2.0,<2.1.1', '2.1.1'],
    'open ended range' => ['5.0.0', '>=5.0', null],
    'inclusive upper bound' => ['1.0.0', '<=1.0.0', null],
    'not affected' => ['3.0.0', '>=1.0,<1.2.3', null],
]);

test('fixed version from osv events', function (string $installedVersion, array $events, string $package, ?string $expected): void {
    $vulnerability = [
        'affected' => [
            [
                'package' => ['name' => 'other-package', 'ecosystem' => 'npm'],
                'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['fixed' => '9.9.9']]]],
            ],
            [
                'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
                'ranges' => [['type' => 'SEMVER', 'events' => $events]],
            ],
        ],
    ];

    expect(FixedVersion::fromOsv($installedVersion, $vulnerability, $package))->toBe($expected);
})->with([
    'single range' => ['2.3.1', [['introduced' => '0'], ['fixed' => '2.3.2']], 'left-pad', '2.3.2'],
    'second range' => ['3.0.0', [['introduced' => '0'], ['fixed' => '2.3.2'], ['introduced' => '3.0.0'], ['fixed' => '3.0.1']], 'left-pad', '3.0.1'],
    'last affected only' => ['2.3.1', [['introduced' => '0'], ['last_affected' => '2.3.1']], 'left-pad', null],
    'other package ignored' => ['2.3.1', [['introduced' => '0'], ['fixed' => '2.3.2']], 'unknown-package', null],
]);

test('osv git ranges are ignored', function (): void {
    $vulnerability = [
        'affected' => [[
            'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
            'ranges' => [['type' => 'GIT', 'events' => [['introduced' => '0'], ['fixed' => '2.3.2']]]],
        ]],
    ];

    expect(FixedVersion::fromOsv('2.3.1', $vulnerability, 'left-pad'))->toBeNull()
        ->and(FixedVersion::hasOsvRanges($vulnerability, 'left-pad'))->toBeFalse();
});

test('osv ranges are detected per package', function (): void {
    $vulnerability = [
        'affected' => [[
            'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
            'ranges' => [['type' => 'ECOSYSTEM', 'events' => [['introduced' => '0']]]],
        ]],
    ];

    expect(FixedVersion::hasOsvRanges($vulnerability, 'left-pad'))->toBeTrue()
        ->and(FixedVersion::hasOsvRanges($vulnerability, 'other-package'))->toBeFalse();
});
