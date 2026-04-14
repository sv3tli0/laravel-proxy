<?php

declare(strict_types=1);

namespace Lararoxy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Lararoxy\Contracts\TrackingStoreContract;

#[Signature('lararoxy:cleanup-tracking {--days= : Days to retain}')]
#[Description('Clean up old tracked request records')]
class CleanupTrackingCommand extends Command
{
    public function handle(TrackingStoreContract $store): int
    {
        $days = (int) ($this->option('days') ?? config('lararoxy.tracking.retention_days', 90));

        $deleted = $store->cleanup($days);

        $this->info("Cleaned up {$deleted} tracked request records older than {$days} days.");

        return self::SUCCESS;
    }
}
