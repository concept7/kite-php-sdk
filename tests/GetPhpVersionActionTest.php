<?php

use Concept7\Kite\Actions\GetPhpVersionAction;
use Illuminate\Support\Collection;

test('adds php_version key with current php version', function () {
    $result = (new GetPhpVersionAction)->handle(new Collection, fn ($data) => $data);

    expect($result->toArray())->toBe([
        ['key' => 'php_version', 'value' => phpversion()],
    ]);
});

test('passes existing collection items through', function () {
    $collection = new Collection([['key' => 'existing', 'value' => '1.0']]);

    $result = (new GetPhpVersionAction)->handle($collection, fn ($data) => $data);

    expect($result)->toHaveCount(2)
        ->and($result[0]['key'])->toBe('existing')
        ->and($result[1]['key'])->toBe('php_version');
});
