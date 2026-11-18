<?php

use Concept7\Kite\Http\Integrations\Osv\OsvConnector;
use Concept7\Kite\Http\Integrations\Packagist\PackagistConnector;
use Saloon\MockConfig;

MockConfig::setFixturePath(__DIR__.'/Fixtures/Saloon');

uses()
    ->beforeEach(function (): void {
        PackagistConnector::$retryIntervalInMilliseconds = 0;
        OsvConnector::$retryIntervalInMilliseconds = 0;
    })
    ->in(__DIR__);
