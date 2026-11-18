<?php

namespace Concept7\Kite\Http\Integrations\Concerns;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;

trait RetriesServerErrors
{
    public static int $retryIntervalInMilliseconds = 2000;

    public ?int $tries = 3;

    public ?bool $useExponentialBackoff = true;

    public ?bool $throwOnMaxTries = false;

    public function __construct()
    {
        $this->retryInterval = static::$retryIntervalInMilliseconds;
    }

    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        return $exception instanceof FatalRequestException || $exception->getResponse()->serverError();
    }
}
