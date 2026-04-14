<?php

declare(strict_types=1);

namespace Lararoxy\Http;

use Illuminate\Http\Client\PendingRequest;
use Lararoxy\Contracts\ServiceAuthenticator;

class ApiKeyAuthenticator implements ServiceAuthenticator
{
    public function __construct(
        protected string $key,
        protected string $header = 'X-Api-Key',
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        return $request->withHeaders([$this->header => $this->key]);
    }
}
