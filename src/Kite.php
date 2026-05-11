<?php

namespace Concept7\Kite;

use Concept7\Kite\Actions\GetKiteVersionAction;
use Concept7\Kite\Actions\GetMysqlVersionAction;
use Concept7\Kite\Actions\GetNodeVersionAction;
use Concept7\Kite\Actions\GetPhpVersionAction;
use Concept7\Kite\Actions\GetTailwindVersionAction;
use Concept7\Kite\Contracts\ActionInterface;
use Concept7\Kite\Contracts\ProjectInfoCollectorInterface;
use Concept7\Kite\Http\Integrations\Kite\Dtos\ProjectReportDto;
use Concept7\Kite\Http\Integrations\Kite\KiteConnector;
use Concept7\Kite\Http\Integrations\Kite\Requests\AdvisoriesRequest;
use Concept7\Kite\Http\Integrations\Kite\Requests\ReportRequest;
use Concept7\Kite\Support\ComposerAdvisories;
use Concept7\Kite\Support\NpmAdvisories;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;

class Kite
{
    private array $actions = [];

    private ?ProjectInfoCollectorInterface $projectInfoCollector = null;

    private function __construct(private KiteConfig $config)
    {
        $this->actions = $this->defaultActions();
    }

    public static function make(KiteConfig $config): self
    {
        return new self($config);
    }

    public function projectInfoCollector(ProjectInfoCollectorInterface $collector): self
    {
        $this->projectInfoCollector = $collector;

        return $this;
    }

    public function defaultActions(): array
    {
        return [
            new GetPhpVersionAction,
            new GetNodeVersionAction,
            new GetMysqlVersionAction,
            new GetTailwindVersionAction,
            new GetKiteVersionAction,
        ];
    }

    public function addAction(ActionInterface $action): self
    {
        $this->actions[] = $action;

        return $this;
    }

    public function addActions(array $actions): self
    {
        foreach ($actions as $action) {
            $this->addAction($action);
        }

        return $this;
    }

    public function setActions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    public function report(): ProjectReportDto
    {
        if (! $this->config->isValid()) {
            throw new \Exception('Project credentials are missing!');
        }

        $meta = (new Pipeline)
            ->send(new Collection)
            ->through($this->actions)
            ->thenReturn();

        $metaPayload = $meta
            ->filter(fn (array $record): bool => filled(data_get($record, 'value')))
            ->values()
            ->toArray();

        $payload = [
            'meta' => $metaPayload,
        ];

        if ($this->projectInfoCollector) {
            $projectInfo = $this->projectInfoCollector->collect();
            $packages = data_get($projectInfo, 'packages', []);

            if (filled($this->config->monitoredPackages)) {
                $packagesByName = array_column($packages, null, 'name');

                $monitored = array_values(array_filter(array_map(
                    fn (string $name): ?array => isset($packagesByName[$name])
                        ? [
                            'name' => $name,
                            'version' => $packagesByName[$name]['version'],
                            'ecosystem' => $packagesByName[$name]['ecosystem'] ?? 'composer',
                        ]
                        : null,
                    $this->config->monitoredPackages,
                )));

                if (filled($monitored)) {
                    $projectInfo['monitored_packages'] = $monitored;
                }
            }

            $payload['project_info'] = $projectInfo;

            if (filled($packages)) {
                try {
                    $payload['advisories'] = array_merge(
                        ComposerAdvisories::scan($packages),
                        NpmAdvisories::scan($packages),
                    );
                } catch (\Throwable) {
                    // advisory scan failure must not block the report
                }
            }
        }

        $connector = new KiteConnector($this->config);

        $request = new ReportRequest($payload);
        $response = $connector->send($request);

        return $response->dtoOrFail();
    }

    public function checkAdvisories(): void
    {
        if (! $this->config->isValid()) {
            throw new \Exception('Project credentials are missing!');
        }

        if (! $this->projectInfoCollector) {
            return;
        }

        $packages = data_get($this->projectInfoCollector->collect(), 'packages', []);

        if (blank($packages)) {
            return;
        }

        try {
            $advisories = array_merge(
                ComposerAdvisories::scan($packages),
                NpmAdvisories::scan($packages),
            );
        } catch (\Throwable) {
            return;
        }

        $connector = new KiteConnector($this->config);
        $connector->send(new AdvisoriesRequest($advisories));
    }
}
