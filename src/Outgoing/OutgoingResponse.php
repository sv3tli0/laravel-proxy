<?php

declare(strict_types=1);

namespace Lararoxy\Outgoing;

use Illuminate\Http\Client\Response;

class OutgoingResponse
{
    public function __construct(
        protected Response $response,
        protected ?string $trackingId = null,
    ) {}

    public function trackingId(): ?string
    {
        return $this->trackingId;
    }

    public function status(): int
    {
        return $this->response->status();
    }

    public function body(): string
    {
        return $this->response->body();
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        return $this->response->json($key, $default);
    }

    public function ok(): bool
    {
        return $this->response->ok();
    }

    public function successful(): bool
    {
        return $this->response->successful();
    }

    public function failed(): bool
    {
        return $this->response->failed();
    }

    public function headers(): array
    {
        return $this->response->headers();
    }

    public function response(): Response
    {
        return $this->response;
    }
}
