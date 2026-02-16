<?php

namespace Concept7\Kite\Http\Integrations\KiteConnector;

use Concept7\Kite\KiteConfig;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class KiteConnector extends Connector
{
    use AcceptsJson;

    public function __construct(private KiteConfig $config) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->config->uri, '/');
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->config->projectKey);
    }
}
