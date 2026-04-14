<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class CallbackConfig
{
    public function __construct(
        public string $path,
        public ?string $signatureHeader = null,
        public ?string $signatureVerifier = null,
        public ?string $handler = null,
    ) {}

    public static function fromArray(array $config): static
    {
        return new self(
            path: $config['path'],
            signatureHeader: $config['signature_header'] ?? null,
            signatureVerifier: $config['signature_verifier'] ?? null,
            handler: $config['handler'] ?? null,
        );
    }
}
