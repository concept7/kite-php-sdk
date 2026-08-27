# Kite PHP SDK

Framework-agnostic PHP client for [Kite](https://kite-monitor.com) monitoring. Collects project metadata (PHP version, database, frontend tooling, installed packages) and reports it to the Kite API.

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
composer require concept7/kite-php-sdk
```

## Configuration

Set the `KITE_TOKEN` environment variable with the token generated from the [Kite Dashboard](https://kite-monitor.com/).

```php
use Concept7\Kite\Kite;
use Concept7\Kite\KiteConfig;

$config = new KiteConfig(
    token: getenv('KITE_TOKEN'),
);
```

The API base URL defaults to `https://kite-monitor.com`. Override it for development:

```php
$config = new KiteConfig(
    token: getenv('KITE_TOKEN'),
    uri: 'https://kite.test',
);
```

## Usage

### Basic

```php
$result = Kite::make($config)->report();
```

`Kite::make()` automatically registers the following default actions:

- `GetPhpVersionAction` — PHP version
- `GetMysqlVersionAction` — MySQL/MariaDB version
- `GetTailwindVersionAction` — Tailwind CSS version (from `package-lock.json`)
- `GetKiteVersionAction` — Kite SDK version

### Adding extra actions

Add project-specific actions alongside the defaults:

```php
use Concept7\Kite\Actions\GetComposerPackageVersionAction;

$result = Kite::make($config)
    ->addAction(new GetComposerPackageVersionAction(
        metaKey: 'statamic_version',
        packages: ['statamic/cms'],
    ))
    ->report();
```

Or multiple at once:

```php
$result = Kite::make($config)
    ->addActions([
        new GetComposerPackageVersionAction('statamic_version', ['statamic/cms']),
        new GetComposerPackageVersionAction('livewire_version', ['livewire/livewire']),
    ])
    ->report();
```

### Replacing default actions

Use `setActions()` to completely override the default actions:

```php
$result = Kite::make($config)
    ->setActions([
        new GetPhpVersionAction,
        new MyCustomAction,
    ])
    ->report();
```

### Project info collector

Add a `ProjectInfoCollectorInterface` implementation to send additional project information:

```php
use Concept7\Kite\Contracts\ProjectInfoCollectorInterface;

class MyProjectInfoCollector implements ProjectInfoCollectorInterface
{
    public function collect(): array
    {
        return [
            'hostname' => gethostname(),
            'environment' => getenv('APP_ENV') ?: 'production',
        ];
    }
}

$result = Kite::make($config)
    ->projectInfoCollector(new MyProjectInfoCollector)
    ->report();
```

### Package collection

The SDK provides helpers to collect installed packages with ecosystem tagging:

```php
use Concept7\Kite\Support\ComposerDependencies;
use Concept7\Kite\Support\NpmDependencies;

// Direct Composer dependencies (from composer.json require)
ComposerDependencies::direct();

// All Composer dependencies (including transitive)
ComposerDependencies::all();

// Installed npm packages (from package-lock.json)
NpmDependencies::installed();
```

Each returns an array of `['name' => '...', 'version' => '...', 'ecosystem' => Ecosystem::Composer|Npm]`.

### Full fluent chain

```php
$result = Kite::make($config)
    ->projectInfoCollector(new MyProjectInfoCollector)
    ->addAction(new GetComposerPackageVersionAction('statamic_version', ['statamic/cms']))
    ->report();
```

## Writing a custom action

Implement `ActionInterface` to create your own action. Each action receives a `Collection` of metadata records and passes it along via `$next`:

```php
use Closure;
use Concept7\Kite\Contracts\ActionInterface;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Collection;

class GetRedisVersionAction implements ActionInterface
{
    public function handle(Collection $data, Closure $next): Collection
    {
        $process = new ProcessFactory;
        $result = $process->run('redis-server --version');

        if ($result->successful() && preg_match('/v=(\d+\.\d+\.\d+)/', $result->output(), $matches)) {
            $data->push([
                'key' => 'redis_version',
                'value' => $matches[1],
            ]);
        }

        return $next($data);
    }
}
```

Each record is an array with `key` and `value`. Records with a `null` or empty `value` are automatically filtered out before the report is sent.

## Built-in actions

| Action | Meta key | Source |
|---|---|---|
| `GetPhpVersionAction` | `php_version` | `phpversion()` |
| `GetMysqlVersionAction` | `database_version` | `mysql --version` |
| `GetTailwindVersionAction` | `tailwind_version` | `package-lock.json` |
| `GetKiteVersionAction` | `kite_version` | `composer.lock` |
| `GetComposerPackageVersionAction` | configurable | `composer.lock` |
| `GetNodePackageVersionAction` | configurable | `package-lock.json` |

## Ecosystems

The `Ecosystem` enum tags packages by source:

| Case | Value |
|---|---|
| `Ecosystem::Composer` | `composer` |
| `Ecosystem::Npm` | `npm` |
| `Ecosystem::Wordpress` | `wordpress` |

## License

MIT
