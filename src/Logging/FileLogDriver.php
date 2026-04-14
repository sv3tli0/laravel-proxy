<?php

declare(strict_types=1);

namespace Lararoxy\Logging;

use Illuminate\Support\Facades\Log;
use Lararoxy\Contracts\LogDriverContract;

class FileLogDriver implements LogDriverContract
{
    public function __construct(
        protected string $channel = 'lararoxy',
    ) {}

    public function log(array $record): void
    {
        Log::channel($this->channel)->info('proxy_request', $record);
    }

    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        // File-based logs are not queryable — use database driver for querying
        return [];
    }

    public function cleanup(int $olderThanDays, ?int $maxRecords = null): int
    {
        // File log rotation is handled by the logging framework
        return 0;
    }
}
