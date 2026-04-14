<?php

declare(strict_types=1);

namespace Lararoxy\Tracking;

use Lararoxy\Contracts\TrackingStoreContract;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Models\TrackedRequest;

class DatabaseTrackingStore implements TrackingStoreContract
{
    public function store(string $trackingId, array $data): void
    {
        TrackedRequest::create([
            'tracking_id' => $trackingId,
            'service' => $data['service'] ?? '',
            'method' => $data['method'] ?? 'GET',
            'url' => $data['url'] ?? '',
            'status' => $data['status'] ?? TrackingStatus::Pending->value,
            'request_headers' => $data['request_headers'] ?? null,
            'request_body' => $data['request_body'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function find(string $trackingId): ?array
    {
        $record = TrackedRequest::where('tracking_id', $trackingId)->first();

        if ($record === null) {
            return null;
        }

        return $record->toArray();
    }

    public function updateStatus(string $trackingId, string $status, array $metadata = []): void
    {
        $record = TrackedRequest::where('tracking_id', $trackingId)->first();

        if ($record === null) {
            return;
        }

        $updates = ['status' => $status];

        if (! empty($metadata)) {
            $updates['metadata'] = array_merge($record->metadata ?? [], $metadata);
        }

        $record->update($updates);
    }

    public function cleanup(int $olderThanDays): int
    {
        return TrackedRequest::where('created_at', '<', now()->subDays($olderThanDays))->delete();
    }
}
