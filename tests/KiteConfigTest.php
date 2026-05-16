<?php

use Concept7\Kite\KiteConfig;

test('isValid returns true when token is set', function () {
    $config = new KiteConfig(token: 'my-secret-token');

    expect($config->isValid())->toBeTrue();
});

test('isValid returns false when token is null', function () {
    $config = new KiteConfig(token: null);

    expect($config->isValid())->toBeFalse();
});

test('isValid returns false when token is empty string', function () {
    $config = new KiteConfig(token: '');

    expect($config->isValid())->toBeFalse();
});

test('uri falls back to base url when not set', function () {
    $config = new KiteConfig(token: 'token');

    expect($config->uri)->toBeNull();
});
