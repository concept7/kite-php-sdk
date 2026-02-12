# Kite

Framework-agnostic PHP client for [Kite](https://gitlab.concept7.nl/workflow/kite) monitoring. Collects project metadata (PHP version, database, frontend tooling, etc.) and reports it to the Kite API.

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
composer require concept7/kite
```

## Usage

### Basic

```php
use Concept7\Kite\Kite;
use Concept7\Kite\KiteConfig;

$config = new KiteConfig(
    uri: 'https://kite.example.com',
    projectId: '1',
    projectKey: 'your-project-key',
    projectRoot: '/var/www/my-project',
);

$result = Kite::make($config)->report();

if ($result->success) {
    echo 'Report sent!';
} else {
    echo 'Error: ' . $result->message;
}
```

`Kite::make()` automatically registers the following default actions:

- `GetPhpVersionAction` — PHP version
- `GetMysqlVersionAction` — MySQL/MariaDB version
- `GetTailwindVersionAction` — Tailwind CSS version (from `package-lock.json`)

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

Add a `ProjectInfoCollectorInterface` implementation to send additional project information (such as hostname, environment, etc.):

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

### Full fluent chain

All methods are chainable:

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
use Illuminate\Support\Collection;

class GetRedisVersionAction implements ActionInterface
{
    public function handle(Collection $data, Closure $next): Collection
    {
        $output = @shell_exec('redis-server --version');

        if ($output && preg_match('/v=(\d+\.\d+\.\d+)/', $output, $matches)) {
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

### Using a custom action

Once written, add the action with `addAction()`:

```php
$result = Kite::make($config)
    ->addAction(new GetRedisVersionAction)
    ->report();
```

Or combine multiple custom actions with `addActions()`:

```php
$result = Kite::make($config)
    ->addActions([
        new GetRedisVersionAction,
        new GetPostgresVersionAction,
    ])
    ->report();
```

These are appended alongside the default actions. To fully replace the defaults, use `setActions()`:

```php
$result = Kite::make($config)
    ->setActions([
        new GetPhpVersionAction,
        new GetRedisVersionAction,
    ])
    ->report();
```

### Built-in actions

| Action | Meta key | Source |
|---|---|---|
| `GetPhpVersionAction` | `php_version` | `phpversion()` |
| `GetMysqlVersionAction` | `database_version` | `mysql --version` |
| `GetTailwindVersionAction` | `tailwind_version` | `package-lock.json` |
| `GetComposerPackageVersionAction` | configurable | `composer.lock` |
| `GetNodePackageVersionAction` | configurable | `package-lock.json` |

## KiteConfig

| Parameter | Type | Required | Description |
|---|---|---|---|
| `uri` | `string` | yes | Base URL of the Kite API |
| `projectId` | `string` | yes | Project ID in Kite |
| `projectKey` | `string` | yes | API key for authentication |
| `projectRoot` | `string` | yes | Path to the project root (for lock files) |
| `phpPath` | `string` | no | Path to the PHP binary (default: `php`) |

## ReportResult

`report()` returns a `ReportResult` with:

- `success` (bool) — whether the report was sent successfully
- `message` (string) — error message on failure
- `statusCode` (int) — HTTP status code of the API response

## License

MIT
