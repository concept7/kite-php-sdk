<?php

namespace Concept7\Kite\Http;

use Concept7\Kite\KiteConfig;
use Concept7\Kite\ReportResult;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class KiteHttpClient
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'verify' => false,
        ]);
    }

    public function send(KiteConfig $config, array $payload): ReportResult
    {
        var_dump('xxx');
        try {
            var_dump($payload);
            $response = $this->client->post($config->apiUrl(), [
                'json' => $payload,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$config->projectKey,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return ReportResult::success($statusCode);
            }

            return ReportResult::failure(
                (string) $response->getBody(),
                $statusCode,
            );
        } catch (GuzzleException $e) {
            return ReportResult::failure($e->getMessage());
        }
    }
}
