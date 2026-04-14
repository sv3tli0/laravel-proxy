<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

class WebhookBuilder
{
    protected bool $matchTracking = false;

    protected ?string $signatureHeader = null;

    protected ?string $signatureVerifier = null;

    protected ?string $handler = null;

    public function __construct(
        protected string $path,
    ) {}

    public function matchTracking(): static
    {
        $this->matchTracking = true;

        return $this;
    }

    public function verifySignature(string $header, string $verifierClass): static
    {
        $this->signatureHeader = $header;
        $this->signatureVerifier = $verifierClass;

        return $this;
    }

    public function handle(string $handlerClass): static
    {
        $this->handler = $handlerClass;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'match_tracking' => $this->matchTracking,
            'signature_header' => $this->signatureHeader,
            'signature_verifier' => $this->signatureVerifier,
            'handler' => $this->handler,
        ];
    }
}
