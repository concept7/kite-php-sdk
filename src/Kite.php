<?php

namespace Concept7\Kite;

use Concept7\Kite\Actions\GetMysqlVersionAction;
use Concept7\Kite\Actions\GetPhpVersionAction;
use Concept7\Kite\Actions\GetTailwindVersionAction;
use Concept7\Kite\Contracts\ActionInterface;
use Concept7\Kite\Contracts\ProjectInfoCollectorInterface;
use Concept7\Kite\Http\KiteHttpClient;
use Illuminate\Pipeline\Pipeline;

class Kite
{
    private array $actions = [];

    private KiteHttpClient $httpClient;

    private ?ProjectInfoCollectorInterface $projectInfoCollector = null;

    private function __construct(private KiteConfig $config)
    {
        $this->actions = $this->defaultActions();
        $this->httpClient = new KiteHttpClient;
    }

    public static function make(KiteConfig $config): self
    {
        return new self($config);
    }

    public function setHttpClient(KiteHttpClient $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
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
            new GetMysqlVersionAction,
            new GetTailwindVersionAction,
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

    public function report(): ReportResult
    {
        if (! $this->config->isValid()) {
            return ReportResult::failure('Project credentials are missing!');
        }

        $meta = (new Pipeline)
            ->send(collect([]))
            ->through($this->actions)
            ->thenReturn();

        $payload = [
            'meta' => $meta->filter(fn (array $record) => filled($record['value'] ?? null))->values()->toArray(),
        ];

        if ($this->projectInfoCollector) {
            $payload['project_info'] = $this->projectInfoCollector->collect();
        }

        return $this->httpClient->send($this->config, $payload);
    }
}
