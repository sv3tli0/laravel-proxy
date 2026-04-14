<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

interface TrackingStoreContract
{
    /**
     * Store a new tracked request.
     */
    public function store(string $trackingId, array $data): void;

    /**
     * Find a tracked request by its tracking ID.
     */
    public function find(string $trackingId): ?array;

    /**
     * Update the status of a tracked request.
     */
    public function updateStatus(string $trackingId, string $status, array $metadata = []): void;

    /**
     * Clean up old tracked requests. Returns the number deleted.
     */
    public function cleanup(int $olderThanDays): int;
}
