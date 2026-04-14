<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class CircuitBreakerConfig
{
    public function __construct(
        public bool $enabled = false,
        public int $threshold = 5,
        public int $timeout = 30,
    ) {}

    public static function fromArray(array $config): static
    {
        return new self(
            enabled: $config['enabled'] ?? false,
            threshold: $config['threshold'] ?? 5,
            timeout: $config['timeout'] ?? 30,
        );
    }
}
