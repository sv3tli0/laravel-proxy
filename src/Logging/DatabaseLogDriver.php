<?php

declare(strict_types=1);

namespace Lararoxy\Logging;

use Lararoxy\Contracts\LogDriverContract;
use Lararoxy\Models\ProxyLog;

class DatabaseLogDriver implements LogDriverContract
{
    public function log(array $record): void
    {
        ProxyLog::create($record);
    }

    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $query = ProxyLog::query();

        if (isset($filters['group'])) {
            $query->forGroup($filters['group']);
        }

        if (isset($filters['status_code'])) {
            $query->withStatus($filters['status_code']);
        }

        if (isset($filters['request_id'])) {
            $query->where('request_id', $filters['request_id']);
        }

        if (isset($filters['tracking_id'])) {
            $query->where('tracking_id', $filters['tracking_id']);
        }

        if (isset($filters['since'])) {
            $query->where('created_at', '>=', $filters['since']);
        }

        if (isset($filters['until'])) {
            $query->where('created_at', '<=', $filters['until']);
        }

        return $query
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function cleanup(int $olderThanDays, ?int $maxRecords = null): int
    {
        $deleted = ProxyLog::olderThan($olderThanDays)->delete();

        if ($maxRecords !== null) {
            $total = ProxyLog::count();

            if ($total > $maxRecords) {
                $excess = $total - $maxRecords;
                $deleted += ProxyLog::orderBy('created_at')
                    ->limit($excess)
                    ->delete();
            }
        }

        return $deleted;
    }
}
