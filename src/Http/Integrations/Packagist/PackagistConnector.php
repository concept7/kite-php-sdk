<?php

namespace Concept7\Kite\Http\Integrations\Packagist;

use Concept7\Kite\Http\Integrations\Concerns\RetriesServerErrors;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class PackagistConnector extends Connector
{
    use AcceptsJson;
    use RetriesServerErrors;

    public function resolveBaseUrl(): string
    {
        return 'https://packagist.org';
    }
}
