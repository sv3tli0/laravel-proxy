<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

interface LogDriverContract
{
    /**
     * Store a log record.
     */
    public function log(array $record): void;

    /**
     * Query stored log records.
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array;

    /**
     * Clean up old records. Returns the number of records deleted.
     */
    public function cleanup(int $olderThanDays, ?int $maxRecords = null): int;
}
