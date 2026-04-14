<?php

declare(strict_types=1);

namespace Lararoxy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutgoingRequestFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $serviceName,
        public readonly string $trackingId,
        public readonly string $method,
        public readonly string $url,
        public readonly string $reason,
    ) {}
}
