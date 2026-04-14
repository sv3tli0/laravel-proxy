<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Callback
{
    /**
     * @param  string  $path  Callback URL path (supports {tracking_id})
     * @param  string  $handler  FQCN of CallbackHandler
     * @param  string|null  $signatureHeader  Header containing the signature
     * @param  string|null  $signatureVerifier  FQCN of SignatureVerifier
     */
    public function __construct(
        public string $path,
        public string $handler,
        public ?string $signatureHeader = null,
        public ?string $signatureVerifier = null,
    ) {}
}
