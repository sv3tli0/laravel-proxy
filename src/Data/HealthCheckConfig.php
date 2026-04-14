<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class HealthCheckConfig
{
    public function __construct(
        public string $path = '/health',
        public int $interval = 30,
    ) {}

    public static function fromArray(array $config): static
    {
        return new self(
            path: $config['path'] ?? '/health',
            interval: $config['interval'] ?? 30,
        );
    }
}
