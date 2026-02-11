<?php

namespace Concept7\Kite;

class ReportResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly int $statusCode = 0,
    ) {}

    public static function success(int $statusCode = 200): self
    {
        return new self(
            success: true,
            message: '',
            statusCode: $statusCode,
        );
    }

    public static function failure(string $message, int $statusCode = 0): self
    {
        return new self(
            success: false,
            message: $message,
            statusCode: $statusCode,
        );
    }
}
