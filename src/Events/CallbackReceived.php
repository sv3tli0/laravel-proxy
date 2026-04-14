<?php

declare(strict_types=1);

namespace Lararoxy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class CallbackReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $trackingId,
        public readonly string $serviceName,
        public readonly Request $request,
    ) {}
}
