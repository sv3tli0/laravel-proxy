<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class TrackingConfig
{
    public function __construct(
        public bool $enabled = true,
        public string $store = 'database',
        public int $ttl = 86400,
        public string $idHeader = 'X-Tracking-Id',
    ) {}

    public static function fromArray(array $config): static
    {
        return new self(
            enabled: $config['enabled'] ?? true,
            store: $config['store'] ?? 'database',
            ttl: $config['ttl'] ?? 86400,
            idHeader: $config['id_header'] ?? 'X-Tracking-Id',
        );
    }
}
