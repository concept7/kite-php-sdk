<?php

namespace Concept7\Kite\Http\Integrations\Concerns;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;

/**
 * Retries connection errors and 5xx responses. The retry settings are
 * assigned in the constructor instead of redeclared as properties, because
 * Saloon's Connector already declares them and a redeclaration with another
 * default is a fatal error on newer Saloon releases.
 */
trait RetriesServerErrors
{
    public static int $retryIntervalInMilliseconds = 2000;

    public function __construct()
    {
        $this->tries = 3;
        $this->retryInterval = static::$retryIntervalInMilliseconds;
        $this->useExponentialBackoff = true;
        $this->throwOnMaxTries = false;
    }

    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        return $exception instanceof FatalRequestException || $exception->getResponse()->serverError();
    }
}
