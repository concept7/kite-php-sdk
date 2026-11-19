<?php

use Concept7\Kite\Support\FixedVersion;

test('packagist fixed version distinguishes known, unknown and open ranges', function (string $installedVersion, string $affectedVersions, bool $known, ?string $version): void {
    expect(FixedVersion::forPackagist($installedVersion, $affectedVersions))->toBe([
        'known' => $known,
        'version' => $version,
    ]);
})->with([
    'single range' => ['1.2.0', '>=1.0,<1.2.3', true, '1.2.3'],
    'second range' => ['2.0.5', '>=1.0,<1.2.3|>=2.0,<2.1.1', true, '2.1.1'],
    'exclusive bound after inclusive lower bound' => ['2.10.1', '>=2.0,<2.10.2', true, '2.10.2'],
    'open ended range has no fix' => ['5.0.0', '>=5.0', true, null],
    'inclusive upper bound is unknown' => ['2.10.1', '>=2.0,<=2.10.1', false, null],
    'only an inclusive upper bound is unknown' => ['1.0.0', '<=1.0.0', false, null],
    'not affected is unknown' => ['3.0.0', '>=1.0,<1.2.3', false, null],
]);

test('osv fixed version distinguishes known, unknown and open ranges', function (string $installedVersion, array $events, string $package, bool $known, ?string $version): void {
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

    expect(FixedVersion::forOsv($installedVersion, $vulnerability, $package))->toBe([
        'known' => $known,
        'version' => $version,
    ]);
})->with([
    'single range' => ['2.3.1', [['introduced' => '0'], ['fixed' => '2.3.2']], 'left-pad', true, '2.3.2'],
    'second range' => ['3.0.0', [['introduced' => '0'], ['fixed' => '2.3.2'], ['introduced' => '3.0.0'], ['fixed' => '3.0.1']], 'left-pad', true, '3.0.1'],
    'last affected only is unknown' => ['2.3.1', [['introduced' => '0'], ['last_affected' => '2.3.1']], 'left-pad', false, null],
    'open range has no fix' => ['2.3.1', [['introduced' => '0']], 'left-pad', true, null],
    'installed outside every range is unknown' => ['1.0.0', [['introduced' => '2.0.0'], ['fixed' => '2.3.2']], 'left-pad', false, null],
    'other package ignored' => ['2.3.1', [['introduced' => '0'], ['fixed' => '2.3.2']], 'unknown-package', false, null],
]);

test('osv git ranges are ignored', function (): void {
    $vulnerability = [
        'affected' => [[
            'package' => ['name' => 'left-pad', 'ecosystem' => 'npm'],
            'ranges' => [['type' => 'GIT', 'events' => [['introduced' => '0'], ['fixed' => '2.3.2']]]],
        ]],
    ];

    expect(FixedVersion::forOsv('2.3.1', $vulnerability, 'left-pad'))->toBe(['known' => false, 'version' => null]);
});
