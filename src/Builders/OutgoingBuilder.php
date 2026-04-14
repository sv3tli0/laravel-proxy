<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

use Lararoxy\Data\CallbackConfig;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\RetryConfig;
use Lararoxy\Data\TrackingConfig;

class OutgoingBuilder
{
    protected string $baseUrl = '';

    protected int $timeout = 30;

    protected ?array $auth = null;

    protected ?RetryConfig $retry = null;

    protected ?TrackingConfig $tracking = null;

    protected ?CallbackConfig $callback = null;

    protected ?array $queue = null;

    protected ?array $logging = null;

    public function __construct(
        protected string $name,
    ) {}

    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function bearerAuth(string $token): static
    {
        $this->auth = ['type' => 'bearer', 'token' => $token];

        return $this;
    }

    public function hmacAuth(string $key, string $algorithm = 'sha256'): static
    {
        $this->auth = ['type' => 'hmac', 'key' => $key, 'algorithm' => $algorithm];

        return $this;
    }

    public function apiKeyAuth(string $key, string $header = 'X-Api-Key'): static
    {
        $this->auth = ['type' => 'api-key', 'key' => $key, 'header' => $header];

        return $this;
    }

    public function retry(int $times = 3, int $delay = 1000, int $multiplier = 2): static
    {
        $this->retry = new RetryConfig($times, $delay, $multiplier);

        return $this;
    }

    public function tracking(string $store = 'database', int $ttl = 86400, string $idHeader = 'X-Tracking-Id'): static
    {
        $this->tracking = new TrackingConfig(
            store: $store,
            ttl: $ttl,
            idHeader: $idHeader,
        );

        return $this;
    }

    public function callback(string $path, string $handler, ?string $signatureHeader = null, ?string $signatureVerifier = null): static
    {
        $this->callback = new CallbackConfig(
            path: $path,
            handler: $handler,
            signatureHeader: $signatureHeader,
            signatureVerifier: $signatureVerifier,
        );

        return $this;
    }

    public function queued(?string $connection = null, ?string $queue = null): static
    {
        $this->queue = ['enabled' => true, 'connection' => $connection, 'queue' => $queue];

        return $this;
    }

    public function logging(string $level): static
    {
        $this->logging = ['level' => $level];

        return $this;
    }

    public function build(): OutgoingServiceDefinition
    {
        return new OutgoingServiceDefinition(
            name: $this->name,
            baseUrl: $this->baseUrl,
            timeout: $this->timeout,
            auth: $this->auth,
            tracking: $this->tracking,
            callback: $this->callback,
            retry: $this->retry,
            queue: $this->queue,
            logging: $this->logging,
        );
    }
}
