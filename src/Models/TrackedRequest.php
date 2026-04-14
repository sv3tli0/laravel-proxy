<?php

declare(strict_types=1);

namespace Lararoxy\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Lararoxy\Enums\TrackingStatus;

#[Table(name: 'tracked_requests')]
#[Unguarded]
class TrackedRequest extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'status' => TrackingStatus::class,
            'request_headers' => 'array',
            'callback_payload' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && ! $this->isTerminal();
    }

    public function scopeForService(Builder $query, string $service): Builder
    {
        return $query->where('service', $service);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TrackingStatus::Pending);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now())
            ->whereNotIn('status', [
                TrackingStatus::Processed,
                TrackingStatus::Failed,
                TrackingStatus::FailedToSend,
                TrackingStatus::Expired,
            ]);
    }
}
