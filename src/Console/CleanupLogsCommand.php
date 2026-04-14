<?php

declare(strict_types=1);

namespace Lararoxy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Lararoxy\Contracts\LogDriverContract;

#[Signature('lararoxy:cleanup-logs {--days= : Days to retain} {--max= : Maximum records to keep}')]
#[Description('Clean up old proxy log records')]
class CleanupLogsCommand extends Command
{
    public function handle(LogDriverContract $driver): int
    {
        $days = (int) ($this->option('days') ?? config('lararoxy.logging.retention.days', 30));
        $max = $this->option('max') !== null
            ? (int) $this->option('max')
            : (config('lararoxy.logging.retention.max_records'));

        $deleted = $driver->cleanup($days, $max);

        $this->info("Cleaned up {$deleted} proxy log records older than {$days} days.");

        return self::SUCCESS;
    }
}
