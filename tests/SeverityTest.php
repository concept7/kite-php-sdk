<?php

use Concept7\Kite\Enums\Severity;

test('parse returns correct case for known values', function (string $input, Severity $expected) {
    expect(Severity::parse($input))->toBe($expected);
})->with([
    ['critical', Severity::Critical],
    ['high', Severity::High],
    ['medium', Severity::Medium],
    ['moderate', Severity::Medium],
    ['low', Severity::Low],
]);

test('parse is case insensitive', function () {
    expect(Severity::parse('CRITICAL'))->toBe(Severity::Critical);
    expect(Severity::parse('High'))->toBe(Severity::High);
});

test('parse returns null for unknown values', function () {
    expect(Severity::parse('unknown'))->toBeNull();
    expect(Severity::parse('info'))->toBeNull();
});

test('parse returns null for null input', function () {
    expect(Severity::parse(null))->toBeNull();
});

test('parse returns null for empty string', function () {
    expect(Severity::parse(''))->toBeNull();
});

test('parse returns enum when already an instance', function () {
    expect(Severity::parse(Severity::High))->toBe(Severity::High);
});
