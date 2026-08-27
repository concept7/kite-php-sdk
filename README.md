# Kite PHP SDK

Framework-agnostic PHP client for [Kite](https://kite-monitor.com) monitoring. Collects project metadata (PHP, Node and database versions, installed packages), reports it to the Kite API, and scans those packages for known security advisories.

This is the core SDK. For a framework integration, use [`concept7/laravel-kite`](https://github.com/concept7/laravel-kite) or [`concept7/wordpress-kite`](https://github.com/concept7/wordpress-kite) instead — both build on this package.

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

A config without a token is invalid, and `report()` and `checkAdvisories()` both throw an `Exception` rather than sending anything.

## Usage

### Reporting

```php
$report = Kite::make($config)->report();

echo $report->message;
```

`report()` returns a `ProjectReportDto`. It runs every registered action through a pipeline, drops the records whose `value` came back empty, and posts the result.

`Kite::make()` registers these actions by default:

- `GetPhpVersionAction` — PHP version
- `GetNodeVersionAction` — Node version
- `GetMysqlVersionAction` — MySQL/MariaDB version

### Adding extra actions

Add your own actions alongside the defaults — see [Writing a custom action](#writing-a-custom-action) for what one looks like:

```php
$report = Kite::make($config)
    ->addAction(new GetRedisVersionAction)
    ->report();
```

Or multiple at once:

```php
$report = Kite::make($config)
    ->addActions([
        new GetRedisVersionAction,
        new GetStatamicVersionAction,
    ])
    ->report();
```

### Replacing default actions

Use `setActions()` to completely override the defaults:

```php
use Concept7\Kite\Actions\GetPhpVersionAction;

$report = Kite::make($config)
    ->setActions([
        new GetPhpVersionAction,
        new MyCustomAction,
    ])
    ->report();
```

### Project info collector

Packages and project details are only sent when a `ProjectInfoCollectorInterface` implementation is registered. Its `collect()` returns an array that becomes the `project_info` payload; a `packages` key in it is what gets scanned and filtered.

```php
use Concept7\Kite\Contracts\ProjectInfoCollectorInterface;
use Concept7\Kite\Support\ComposerDependencies;
use Concept7\Kite\Support\NpmDependencies;

class MyProjectInfoCollector implements ProjectInfoCollectorInterface
{
    public function collect(): array
    {
        return [
            'hostname' => gethostname(),
            'environment' => getenv('APP_ENV') ?: 'production',
            'packages' => array_merge(
                ComposerDependencies::all(),
                NpmDependencies::installed(),
            ),
        ];
    }
}

$report = Kite::make($config)
    ->projectInfoCollector(new MyProjectInfoCollector)
    ->report();
```

### Package collection

Helpers to collect installed packages with ecosystem tagging:

```php
// Direct Composer dependencies (from composer.json require)
ComposerDependencies::direct();

// All Composer dependencies, including transitive ones
ComposerDependencies::all();

// All npm packages, from package-lock.json
NpmDependencies::installed();
```

Each accepts an optional base path and defaults to `getcwd()`. Every entry has this shape:

```php
[
    'name' => 'vendor/package',
    'version' => '1.2.3',
    'ecosystem' => Ecosystem::Composer,
    'is_direct' => true,
    'required_by' => ['vendor/other-package'],
]
```

### Package filtering

Before sending, `report()` fetches the project's config from the Kite API. Unless the project is set to share all packages, the reported list is filtered down to the packages Kite is configured to monitor. The advisory scan always runs against the *full* list, so vulnerable transitive packages still surface.

If that config call fails, nothing is filtered out of the scan and no packages are reported.

## Security advisories

`report()` scans the collected packages and includes the findings in the payload. A failing scan is swallowed — it never blocks the report.

To scan without sending a full report — for a scheduled re-scan between reports, say:

```php
Kite::make($config)
    ->projectInfoCollector(new MyProjectInfoCollector)
    ->checkAdvisories();
```

`checkAdvisories()` returns early when no collector is registered or the collector reports no packages.

The scanners can also be used directly:

```php
use Concept7\Kite\Support\ComposerAdvisories;
use Concept7\Kite\Support\NpmAdvisories;

ComposerAdvisories::scan($packages);
NpmAdvisories::scan($packages);
```

Each picks out the packages belonging to its own ecosystem and ignores the rest:

| Scanner | Ecosystem | Source |
|---|---|---|
| `ComposerAdvisories` | `composer` | [Packagist](https://packagist.org) advisories API, version-matched with `composer/semver` |
| `NpmAdvisories` | `npm` | [OSV.dev](https://osv.dev) batch query API |

Both return advisories in the same shape:

```php
[
    'advisory_id' => 'PKSA-xxxx-xxxx-xxxx',
    'package' => 'vendor/package',
    'version' => '1.2.3',
    'ecosystem' => 'composer',
    'title' => 'Advisory title',
    'link' => 'https://...',
    'cve' => 'CVE-2026-0000',
    'severity' => 'high',
    'reported_at' => '2026-08-27',
]
```

`severity` is normalised through the `Severity` enum (`critical`, `high`, `medium`, `low`); OSV's `moderate` maps to `medium`, and anything unrecognised becomes `null`.

## Writing a custom action

Implement `ActionInterface`. Each action receives a `Collection` of metadata records and passes it along via `$next`:

```php
use Closure;
use Concept7\Kite\Contracts\ActionInterface;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class GetRedisVersionAction implements ActionInterface
{
    public function handle(Collection $data, Closure $next): Collection
    {
        $process = new Process(['redis-server', '--version']);
        $process->run();

        if ($process->isSuccessful() && preg_match('/v=(\d+\.\d+\.\d+)/', $process->getOutput(), $matches)) {
            $data->push([
                'key' => 'redis_version',
                'value' => $matches[1],
            ]);
        }

        return $next($data);
    }
}
```

Each record is an array with `key` and `value`. Records with a `null` or empty `value` are filtered out before the report is sent, so an action that cannot determine its value can simply push nothing — or push `null` and let it be dropped.

## Built-in actions

| Action | Meta key | Source |
|---|---|---|
| `GetPhpVersionAction` | `php_version` | `phpversion()` |
| `GetNodeVersionAction` | `node_version` | `node --version`, falling back to `.nvmrc` |
| `GetMysqlVersionAction` | `database_version` | `mysql --version`, prefixed `mysql_` or `mariadb_` |

## Ecosystems

The `Ecosystem` enum tags packages by source:

| Case | Value |
|---|---|
| `Ecosystem::Composer` | `composer` |
| `Ecosystem::Npm` | `npm` |
| `Ecosystem::Wordpress` | `wordpress` |

## Testing

```bash
composer test
composer lint
```

## License

MIT
