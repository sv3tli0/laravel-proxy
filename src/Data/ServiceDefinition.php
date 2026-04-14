<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class ServiceDefinition
{
    public function __construct(
        public string $name,
        public string $baseUrl,
        public int $timeout = 30,
        public int $connectTimeout = 5,
        public ?RetryConfig $retry = null,
        public ?CircuitBreakerConfig $circuitBreaker = null,
        public ?HealthCheckConfig $healthCheck = null,
        public ?array $auth = null,
    ) {}

    public static function fromArray(string $name, array $config): static
    {
        return new self(
            name: $name,
            baseUrl: $config['base_url'],
            timeout: $config['timeout'] ?? 30,
            connectTimeout: $config['connect_timeout'] ?? 5,
            retry: isset($config['retry']) ? RetryConfig::fromArray($config['retry']) : null,
            circuitBreaker: isset($config['circuit_breaker']) ? CircuitBreakerConfig::fromArray($config['circuit_breaker']) : null,
            healthCheck: isset($config['health_check']) ? HealthCheckConfig::fromArray($config['health_check']) : null,
            auth: $config['auth'] ?? null,
        );
    }
}
