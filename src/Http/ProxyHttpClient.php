<?php

declare(strict_types=1);

namespace Lararoxy\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Lararoxy\Contracts\ServiceAuthenticator;
use Lararoxy\Data\RetryConfig;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Exceptions\UpstreamException;

class ProxyHttpClient
{
    public function __construct(
        protected CircuitBreakerManager $circuitBreaker,
    ) {}

    /**
     * Send a request to an upstream service.
     */
    public function send(
        ServiceDefinition $service,
        string $method,
        string $path,
        array $headers = [],
        mixed $body = null,
        ?ServiceAuthenticator $authenticator = null,
    ): Response {
        // Circuit breaker check
        if ($service->circuitBreaker !== null) {
            $this->circuitBreaker->check($service->name, $service->circuitBreaker);
        }

        $request = $this->buildRequest($service, $headers, $authenticator);

        // Apply retry policy
        if ($service->retry !== null) {
            $request = $this->applyRetry($request, $service->retry);
        }

        $url = rtrim($service->baseUrl, '/').'/'.ltrim($path, '/');

        try {
            $response = $request->send($method, $url, $this->buildOptions($body));
        } catch (\Exception $e) {
            $this->recordFailure($service);

            throw new UpstreamException(
                serviceName: $service->name,
                statusCode: 0,
                responseBody: $e->getMessage(),
                previous: $e,
            );
        }

        if ($response->serverError()) {
            $this->recordFailure($service);
        } else {
            $this->recordSuccess($service);
        }

        return $response;
    }

    protected function buildRequest(
        ServiceDefinition $service,
        array $headers,
        ?ServiceAuthenticator $authenticator,
    ): PendingRequest {
        $request = Http::timeout($service->timeout)
            ->connectTimeout($service->connectTimeout)
            ->withHeaders($headers);

        if ($authenticator !== null) {
            $request = $authenticator->authenticate($request);
        }

        return $request;
    }

    protected function applyRetry(PendingRequest $request, RetryConfig $retry): PendingRequest
    {
        return $request->retry(
            times: $retry->times,
            sleepMilliseconds: $retry->delay,
            when: fn ($exception, $request) => $this->shouldRetry($exception, $retry->on),
            throw: false,
        );
    }

    protected function shouldRetry(\Exception $exception, array $retryOn): bool
    {
        if ($exception instanceof RequestException) {
            return in_array($exception->response->status(), $retryOn, true);
        }

        // Retry on connection errors
        return true;
    }

    protected function buildOptions(mixed $body): array
    {
        if ($body === null) {
            return [];
        }

        if (is_array($body)) {
            return ['json' => $body];
        }

        return ['body' => $body];
    }

    protected function recordFailure(ServiceDefinition $service): void
    {
        if ($service->circuitBreaker !== null) {
            $this->circuitBreaker->recordFailure($service->name, $service->circuitBreaker);
        }
    }

    protected function recordSuccess(ServiceDefinition $service): void
    {
        if ($service->circuitBreaker !== null) {
            $this->circuitBreaker->recordSuccess($service->name, $service->circuitBreaker);
        }
    }
}
