<?php

declare(strict_types=1);

namespace Lararoxy\Http;

use Illuminate\Http\Client\PendingRequest;
use Lararoxy\Contracts\ServiceAuthenticator;

class HmacAuthenticator implements ServiceAuthenticator
{
    public function __construct(
        protected string $key,
        protected string $algorithm = 'sha256',
        protected string $header = 'X-Signature',
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        $timestamp = (string) time();
        $signature = hash_hmac($this->algorithm, $timestamp.'.', $this->key);

        return $request->withHeaders([
            $this->header => $signature,
            'X-Timestamp' => $timestamp,
        ]);
    }
}
