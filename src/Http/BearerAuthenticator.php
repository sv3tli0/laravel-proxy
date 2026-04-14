<?php

declare(strict_types=1);

namespace Lararoxy\Http;

use Illuminate\Http\Client\PendingRequest;
use Lararoxy\Contracts\ServiceAuthenticator;

class BearerAuthenticator implements ServiceAuthenticator
{
    public function __construct(
        protected string $token,
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->token);
    }
}
