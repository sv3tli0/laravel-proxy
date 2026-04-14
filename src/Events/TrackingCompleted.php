<?php

declare(strict_types=1);

namespace Lararoxy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lararoxy\Enums\TrackingStatus;

class TrackingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $trackingId,
        public readonly string $serviceName,
        public readonly TrackingStatus $finalStatus,
    ) {}
}
