<?php

namespace Concept7\Kite\Http\Integrations\Osv;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class OsvConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://api.osv.dev/v1';
    }
}
