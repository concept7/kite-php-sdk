<?php

namespace Concept7\Kite\Http\Integrations\Osv;

use Concept7\Kite\Http\Integrations\Concerns\RetriesServerErrors;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class OsvConnector extends Connector
{
    use AcceptsJson;
    use RetriesServerErrors;

    public function resolveBaseUrl(): string
    {
        return 'https://api.osv.dev/v1';
    }
}
