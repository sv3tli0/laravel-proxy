<?php

declare(strict_types=1);

namespace Lararoxy\Exceptions;

class UpstreamException extends LararoxyException
{
    public function __construct(
        public readonly string $serviceName,
        public readonly int $statusCode,
        public readonly ?string $responseBody = null,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $message = $message ?: "Upstream service [{$serviceName}] returned HTTP {$statusCode}.";

        parent::__construct($message, $code, $previous);
    }
}
