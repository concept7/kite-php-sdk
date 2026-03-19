<?php

namespace Concept7\Kite\Http\Integrations\Kite;

use Concept7\Kite\Http\Senders\KiteGuzzleSender;
use Concept7\Kite\KiteConfig;
use Saloon\Contracts\Sender;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class KiteConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public function __construct(private KiteConfig $kiteConfig) {}

    protected function defaultSender(): Sender
    {
        return new KiteGuzzleSender();
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->kiteConfig->uri ?? KiteConfig::BASE_URL, '/');
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->kiteConfig->token);
    }
}
