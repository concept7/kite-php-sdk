<?php

namespace Concept7\Kite\Http\Senders;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Saloon\Config;
use Saloon\Http\Senders\GuzzleSender;

class KiteGuzzleSender extends GuzzleSender
{
    protected function createGuzzleClient(): GuzzleClient
    {
        $this->handlerStack = $this->defaultHandlerStack();

        $options = [
            RequestOptions::CONNECT_TIMEOUT => Config::$defaultConnectionTimeout,
            RequestOptions::TIMEOUT => Config::$defaultRequestTimeout,
            RequestOptions::HTTP_ERRORS => true,
            'handler' => $this->handlerStack,
        ];

        if (defined('GuzzleHttp\RequestOptions::CRYPTO_METHOD')) {
            $options[RequestOptions::CRYPTO_METHOD] = Config::$defaultTlsMethod;
        }

        return new GuzzleClient($options);
    }
}
