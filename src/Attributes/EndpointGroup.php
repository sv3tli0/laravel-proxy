<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class EndpointGroup
{
    /**
     * @param  array<string>  $middleware
     * @param  string|null  $tokenPayload  FQCN of TokenPayload implementation
     */
    public function __construct(
        public string $name,
        public string $prefix = '',
        public ?string $domain = null,
        public array $middleware = [],
        public ?string $tokenPayload = null,
    ) {}
}
