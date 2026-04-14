<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

use Lararoxy\Data\CircuitBreakerConfig;
use Lararoxy\Data\HealthCheckConfig;
use Lararoxy\Data\RetryConfig;
use Lararoxy\Data\ServiceDefinition;

class ServiceBuilder
{
    protected string $baseUrl = '';

    protected int $timeout = 30;

    protected int $connectTimeout = 5;

    protected ?RetryConfig $retry = null;

    protected ?CircuitBreakerConfig $circuitBreaker = null;

    protected ?HealthCheckConfig $healthCheck = null;

    protected ?array $auth = null;

    public function __construct(
        protected string $name,
    ) {}

    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function connectTimeout(int $seconds): static
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    public function retry(int $times = 3, int $delay = 100, int $multiplier = 2, array $on = [500, 502, 503, 504]): static
    {
        $this->retry = new RetryConfig($times, $delay, $multiplier, $on);

        return $this;
    }

    public function circuitBreaker(int $threshold = 5, int $timeout = 30): static
    {
        $this->circuitBreaker = new CircuitBreakerConfig(true, $threshold, $timeout);

        return $this;
    }

    public function healthCheck(string $path = '/health', int $interval = 30): static
    {
        $this->healthCheck = new HealthCheckConfig($path, $interval);

        return $this;
    }

    public function bearerAuth(string $token): static
    {
        $this->auth = ['type' => 'bearer', 'token' => $token];

        return $this;
    }

    public function hmacAuth(string $key, string $algorithm = 'sha256'): static
    {
        $this->auth = ['type' => 'hmac', 'key' => $key, 'algorithm' => $algorithm];

        return $this;
    }

    public function apiKeyAuth(string $key, string $header = 'X-Api-Key'): static
    {
        $this->auth = ['type' => 'api-key', 'key' => $key, 'header' => $header];

        return $this;
    }

    public function build(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: $this->name,
            baseUrl: $this->baseUrl,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            retry: $this->retry,
            circuitBreaker: $this->circuitBreaker,
            healthCheck: $this->healthCheck,
            auth: $this->auth,
        );
    }
}
