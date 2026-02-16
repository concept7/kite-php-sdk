<?php

namespace Concept7\Kite\Http;

use Concept7\Kite\Http\Integrations\KiteConnector\KiteConnector;
use Concept7\Kite\Http\Integrations\KiteConnector\Requests\SendReportRequest;
use Concept7\Kite\KiteConfig;
use Concept7\Kite\ReportResult;

class KiteHttpClient
{
    public function send(KiteConfig $config, array $payload): ReportResult
    {
        try {
            $connector = new KiteConnector($config);
            $request = new SendReportRequest($config->projectId, $payload);

            $response = $connector->send($request);
            $statusCode = $response->status();

            if ($response->ok()) {
                return ReportResult::success($statusCode);
            }

            return ReportResult::failure(
                $response->body(),
                $statusCode,
            );
        } catch (\Throwable $e) {
            return ReportResult::failure($e->getMessage());
        }
    }
}
