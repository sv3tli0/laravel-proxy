<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Illuminate\Http\Client\PendingRequest;

interface ServiceAuthenticator
{
    /**
     * Apply service-to-service authentication to the outgoing request.
     */
    public function authenticate(PendingRequest $request): PendingRequest;
}
