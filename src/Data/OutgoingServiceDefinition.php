<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class OutgoingServiceDefinition
{
    public function __construct(
        public string $name,
        public string $baseUrl,
        public int $timeout = 30,
        public ?array $auth = null,
        public ?TrackingConfig $tracking = null,
        public ?CallbackConfig $callback = null,
        public ?RetryConfig $retry = null,
        public ?array $queue = null,
        public ?array $logging = null,
    ) {}

    public static function fromArray(string $name, array $config): static
    {
        return new self(
            name: $name,
            baseUrl: $config['base_url'],
            timeout: $config['timeout'] ?? 30,
            auth: $config['auth'] ?? null,
            tracking: isset($config['tracking']) ? TrackingConfig::fromArray($config['tracking']) : null,
            callback: isset($config['callback']) ? CallbackConfig::fromArray($config['callback']) : null,
            retry: isset($config['retry']) ? RetryConfig::fromArray($config['retry']) : null,
            queue: $config['queue'] ?? null,
            logging: $config['logging'] ?? null,
        );
    }
}
