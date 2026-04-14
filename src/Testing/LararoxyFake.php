<?php

declare(strict_types=1);

namespace Lararoxy\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;

class LararoxyFake
{
    /** @var array<string, array<array{url: string, method: string, body: mixed}>> */
    protected array $sentRequests = [];

    /** @var array<string, array<array{url: string, method: string, body: mixed}>> */
    protected array $outgoingRequests = [];

    /** @var array<string, string> */
    protected array $trackingStatuses = [];

    /**
     * Record a sent proxy request (for assertion).
     */
    public function recordSent(string $service, string $method, string $url, mixed $body = null): void
    {
        $this->sentRequests[$service][] = [
            'method' => $method,
            'url' => $url,
            'body' => $body,
        ];
    }

    /**
     * Record an outgoing request (for assertion).
     */
    public function recordOutgoing(string $service, string $method, string $url, mixed $body = null): void
    {
        $this->outgoingRequests[$service][] = [
            'method' => $method,
            'url' => $url,
            'body' => $body,
        ];
    }

    /**
     * Record a tracking status (for assertion).
     */
    public function recordTracking(string $trackingId, string $status): void
    {
        $this->trackingStatuses[$trackingId] = $status;
    }

    /**
     * Assert a request was sent to a specific service.
     */
    public function assertSent(string $service, ?\Closure $callback = null): void
    {
        Assert::assertArrayHasKey(
            $service,
            $this->sentRequests,
            "No requests were sent to service [{$service}]."
        );

        if ($callback !== null) {
            $matched = false;

            foreach ($this->sentRequests[$service] as $request) {
                if ($callback((object) $request)) {
                    $matched = true;
                    break;
                }
            }

            Assert::assertTrue($matched, "No matching request found for service [{$service}].");
        }
    }

    /**
     * Assert no request was sent to a specific service.
     */
    public function assertNotSent(string $service): void
    {
        Assert::assertArrayNotHasKey(
            $service,
            $this->sentRequests,
            "Unexpected request was sent to service [{$service}]."
        );
    }

    /**
     * Assert the number of requests sent to a service.
     */
    public function assertSentCount(string $service, int $count): void
    {
        $actual = count($this->sentRequests[$service] ?? []);

        Assert::assertSame(
            $count,
            $actual,
            "Expected {$count} requests to [{$service}], got {$actual}."
        );
    }

    /**
     * Assert an outgoing request was made to a service.
     */
    public function assertOutgoing(string $service, ?\Closure $callback = null): void
    {
        Assert::assertArrayHasKey(
            $service,
            $this->outgoingRequests,
            "No outgoing requests were made to service [{$service}]."
        );

        if ($callback !== null) {
            $matched = false;

            foreach ($this->outgoingRequests[$service] as $request) {
                if ($callback((object) $request)) {
                    $matched = true;
                    break;
                }
            }

            Assert::assertTrue($matched, "No matching outgoing request found for service [{$service}].");
        }
    }

    /**
     * Assert a tracking ID reached a specific status.
     */
    public function assertTracked(string $trackingId, string $status): void
    {
        Assert::assertArrayHasKey(
            $trackingId,
            $this->trackingStatuses,
            "Tracking ID [{$trackingId}] not found."
        );

        Assert::assertSame(
            $status,
            $this->trackingStatuses[$trackingId],
            "Expected tracking [{$trackingId}] to be [{$status}], got [{$this->trackingStatuses[$trackingId]}]."
        );
    }

    /**
     * Create a fake HTTP response for testing.
     */
    public static function response(array|string $body = [], int $status = 200, array $headers = []): PromiseInterface
    {
        return Http::response($body, $status, $headers);
    }
}
