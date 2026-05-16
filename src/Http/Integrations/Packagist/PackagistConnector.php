<?php

namespace Concept7\Kite\Http\Integrations\Packagist;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class PackagistConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://packagist.org';
    }
}
