<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Illuminate\Http\Request;

interface CallbackHandler
{
    /**
     * Handle a verified callback for a tracked outgoing request.
     */
    public function handle(Request $request, object $trackedRequest): void;

    /**
     * Handle a callback that failed signature verification.
     */
    public function onVerificationFailed(Request $request): void;
}
