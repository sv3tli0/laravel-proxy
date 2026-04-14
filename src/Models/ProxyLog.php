<?php

declare(strict_types=1);

namespace Lararoxy\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Lararoxy\Enums\LogLevel;

#[Table(name: 'proxy_logs')]
#[Unguarded]
#[WithoutTimestamps]
class ProxyLog extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'level' => LogLevel::class,
            'request_headers' => 'array',
            'response_headers' => 'array',
            'token_payload' => 'array',
            'tags' => 'array',
            'pipeline_trace' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    public function scopeWithStatus(Builder $query, int $statusCode): Builder
    {
        return $query->where('status_code', $statusCode);
    }
}
