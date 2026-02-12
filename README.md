# Kite

Framework-agnostic PHP client voor [Kite](https://gitlab.concept7.nl/workflow/kite) monitoring. Verzamelt project-metadata (PHP-versie, database, frontend-tooling, etc.) en rapporteert deze aan de Kite API.

## Vereisten

- PHP 8.2+
- Composer

## Installatie

```bash
composer require concept7/kite
```

## Gebruik

### Basis

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
    echo 'Rapport verzonden!';
} else {
    echo 'Fout: ' . $result->message;
}
```

`Kite::make()` registreert automatisch de volgende default actions:

- `GetPhpVersionAction` — PHP-versie
- `GetMysqlVersionAction` — MySQL/MariaDB-versie
- `GetTailwindVersionAction` — Tailwind CSS-versie (uit `package-lock.json`)
- `GetViteVersionAction` — Vite-versie (uit `package-lock.json`)

### Extra actions toevoegen

Voeg project-specifieke actions toe naast de defaults:

```php
use Concept7\Kite\Actions\GetComposerPackageVersionAction;

$result = Kite::make($config)
    ->addAction(new GetComposerPackageVersionAction(
        metaKey: 'statamic_version',
        packages: ['statamic/cms'],
    ))
    ->report();
```

Of meerdere tegelijk:

```php
$result = Kite::make($config)
    ->addActions([
        new GetComposerPackageVersionAction('statamic_version', ['statamic/cms']),
        new GetComposerPackageVersionAction('livewire_version', ['livewire/livewire']),
    ])
    ->report();
```

### Default actions vervangen

Gebruik `setActions()` om de default actions volledig te overschrijven:

```php
$result = Kite::make($config)
    ->setActions([
        new GetPhpVersionAction,
        new MyCustomAction,
    ])
    ->report();
```

### Project info collector

Voeg een `ProjectInfoCollectorInterface`-implementatie toe om extra projectinformatie mee te sturen (zoals hostname, environment, etc.):

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

### Volledige fluent chain

Alle methodes zijn chainable:

```php
$result = Kite::make($config)
    ->projectInfoCollector(new MyProjectInfoCollector)
    ->addAction(new GetComposerPackageVersionAction('statamic_version', ['statamic/cms']))
    ->report();
```

## Custom action schrijven

Implementeer `ActionInterface` om een eigen action te maken. Elke action ontvangt een `Collection` met metadata-records en geeft deze door via `$next`:

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

Elke record is een array met `key` en `value`. Records met een `null` of lege `value` worden automatisch uitgefilterd voordat het rapport wordt verzonden.

### Custom action toepassen

Eenmaal geschreven voeg je de action toe met `addAction()`:

```php
$result = Kite::make($config)
    ->addAction(new GetRedisVersionAction)
    ->report();
```

Of combineer meerdere custom actions met `addActions()`:

```php
$result = Kite::make($config)
    ->addActions([
        new GetRedisVersionAction,
        new GetPostgresVersionAction,
    ])
    ->report();
```

Deze worden toegevoegd naast de default actions. Wil je de defaults volledig vervangen, gebruik dan `setActions()`:

```php
$result = Kite::make($config)
    ->setActions([
        new GetPhpVersionAction,
        new GetRedisVersionAction,
    ])
    ->report();
```

### Ingebouwde actions

| Action | Meta key | Bron |
|---|---|---|
| `GetPhpVersionAction` | `php_version` | `phpversion()` |
| `GetMysqlVersionAction` | `database_version` | `mysql --version` |
| `GetTailwindVersionAction` | `tailwind_version` | `package-lock.json` |
| `GetViteVersionAction` | `vite_version` | `package-lock.json` |
| `GetComposerPackageVersionAction` | configureerbaar | `composer.lock` |
| `GetNodePackageVersionAction` | configureerbaar | `package-lock.json` |

## KiteConfig

| Parameter | Type | Verplicht | Omschrijving |
|---|---|---|---|
| `uri` | `string` | ja | Base-URL van de Kite API |
| `projectId` | `string` | ja | Project-ID in Kite |
| `projectKey` | `string` | ja | API-key voor authenticatie |
| `projectRoot` | `string` | ja | Pad naar de project-root (voor lock-files) |
| `phpPath` | `string` | nee | Pad naar PHP binary (default: `php`) |

## ReportResult

`report()` retourneert een `ReportResult` met:

- `success` (bool) — of het rapport succesvol is verzonden
- `message` (string) — foutmelding bij failure
- `statusCode` (int) — HTTP-statuscode van de API-response

## Licentie

MIT
