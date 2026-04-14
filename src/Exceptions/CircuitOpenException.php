<?php

declare(strict_types=1);

namespace Lararoxy\Exceptions;

class CircuitOpenException extends LararoxyException
{
    public function __construct(
        public readonly string $serviceName,
        public readonly int $retryAfter,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $message = $message ?: "Circuit breaker is open for service [{$serviceName}]. Retry after {$retryAfter}s.";

        parent::__construct($message, $code, $previous);
    }
}
