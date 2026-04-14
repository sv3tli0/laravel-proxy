<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Exceptions;

use Lararoxy\Exceptions\CircuitOpenException;
use Lararoxy\Exceptions\LararoxyException;
use Lararoxy\Exceptions\UpstreamException;
use Lararoxy\Tests\TestCase;

class ExceptionTest extends TestCase
{
    public function test_circuit_open_has_service_name_and_retry_after(): void
    {
        $e = new CircuitOpenException('users-service', 25);

        $this->assertSame('users-service', $e->serviceName);
        $this->assertSame(25, $e->retryAfter);
        $this->assertStringContainsString('users-service', $e->getMessage());
        $this->assertInstanceOf(LararoxyException::class, $e);
    }

    public function test_upstream_exception_has_status_and_body(): void
    {
        $e = new UpstreamException('orders-service', 503, '{"error":"timeout"}');

        $this->assertSame('orders-service', $e->serviceName);
        $this->assertSame(503, $e->statusCode);
        $this->assertSame('{"error":"timeout"}', $e->responseBody);
        $this->assertStringContainsString('503', $e->getMessage());
    }

    public function test_upstream_exception_accepts_custom_message(): void
    {
        $e = new UpstreamException('svc', 500, null, 'Custom error');

        $this->assertSame('Custom error', $e->getMessage());
    }
}
