<?php

declare(strict_types=1);

namespace Lararoxy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Models\TrackedRequest;

#[Signature('lararoxy:expire-tracking')]
#[Description('Mark TTL-exceeded tracked requests as expired')]
class ExpireTrackingCommand extends Command
{
    public function handle(): int
    {
        $count = 0;

        TrackedRequest::expired()
            ->chunkById(100, function ($records) use (&$count) {
                foreach ($records as $record) {
                    $record->update(['status' => TrackingStatus::Expired]);
                    $count++;
                }
            });

        $this->info("Marked {$count} tracked requests as expired.");

        return self::SUCCESS;
    }
}
