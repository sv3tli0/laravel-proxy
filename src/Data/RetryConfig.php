<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class RetryConfig
{
    public function __construct(
        public int $times = 3,
        public int $delay = 100,
        public int $multiplier = 2,
        public array $on = [500, 502, 503, 504],
    ) {}

    public static function fromArray(array $config): static
    {
        return new self(
            times: $config['times'] ?? 3,
            delay: $config['delay'] ?? 100,
            multiplier: $config['multiplier'] ?? 2,
            on: $config['on'] ?? [500, 502, 503, 504],
        );
    }
}
