<?php

use Concept7\Kite\Actions\GetNodeVersionAction;
use Illuminate\Support\Collection;

test('always pushes node_version key', function () {
    $result = (new GetNodeVersionAction)->handle(new Collection, fn ($data) => $data);

    expect($result[0]['key'])->toBe('node_version');
});

test('reads version from .nvmrc when node binary unavailable', function () {
    $dir = sys_get_temp_dir().'/kite-sdk-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/.nvmrc', '20.11.0');

    // Simulate missing binary by using a subclass that skips shell_exec
    $action = new class($dir) extends GetNodeVersionAction
    {
        protected function resolveVersionFromBinary(): ?string
        {
            return null;
        }
    };

    $result = $action->handle(new Collection, fn ($data) => $data);

    expect($result[0]['value'])->toBe('20.11.0');

    unlink($dir.'/.nvmrc');
    rmdir($dir);
});

test('strips leading v from .nvmrc version', function () {
    $dir = sys_get_temp_dir().'/kite-sdk-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/.nvmrc', 'v20.11.0');

    $action = new class($dir) extends GetNodeVersionAction
    {
        protected function resolveVersionFromBinary(): ?string
        {
            return null;
        }
    };

    $result = $action->handle(new Collection, fn ($data) => $data);

    expect($result[0]['value'])->toBe('20.11.0');

    unlink($dir.'/.nvmrc');
    rmdir($dir);
});

test('returns null when no node binary and no .nvmrc', function () {
    $dir = sys_get_temp_dir().'/kite-sdk-test-'.uniqid();
    mkdir($dir);

    $action = new class($dir) extends GetNodeVersionAction
    {
        protected function resolveVersionFromBinary(): ?string
        {
            return null;
        }
    };

    $result = $action->handle(new Collection, fn ($data) => $data);

    expect($result[0]['value'])->toBeNull();

    rmdir($dir);
});

test('ignores .nvmrc with non-semver content like lts/*', function () {
    $dir = sys_get_temp_dir().'/kite-sdk-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/.nvmrc', 'lts/*');

    $action = new class($dir) extends GetNodeVersionAction
    {
        protected function resolveVersionFromBinary(): ?string
        {
            return null;
        }
    };

    $result = $action->handle(new Collection, fn ($data) => $data);

    expect($result[0]['value'])->toBeNull();

    unlink($dir.'/.nvmrc');
    rmdir($dir);
});
