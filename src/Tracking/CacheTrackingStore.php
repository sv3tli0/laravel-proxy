<?php

declare(strict_types=1);

namespace Lararoxy\Tracking;

use Illuminate\Support\Facades\Cache;
use Lararoxy\Contracts\TrackingStoreContract;

class CacheTrackingStore implements TrackingStoreContract
{
    public function __construct(
        protected int $defaultTtl = 86400,
        protected string $prefix = 'lararoxy:tracking:',
    ) {}

    public function store(string $trackingId, array $data): void
    {
        $ttl = $data['ttl'] ?? $this->defaultTtl;

        Cache::put($this->key($trackingId), $data, $ttl);
    }

    public function find(string $trackingId): ?array
    {
        return Cache::get($this->key($trackingId));
    }

    public function updateStatus(string $trackingId, string $status, array $metadata = []): void
    {
        $data = $this->find($trackingId);

        if ($data === null) {
            return;
        }

        $data['status'] = $status;

        if (! empty($metadata)) {
            $data['metadata'] = array_merge($data['metadata'] ?? [], $metadata);
        }

        $ttl = $data['ttl'] ?? $this->defaultTtl;
        Cache::put($this->key($trackingId), $data, $ttl);
    }

    public function cleanup(int $olderThanDays): int
    {
        // Cache entries expire automatically via TTL
        return 0;
    }

    protected function key(string $trackingId): string
    {
        return $this->prefix.$trackingId;
    }
}
