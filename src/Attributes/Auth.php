<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Auth
{
    /**
     * @param  string  $driver  Auth driver name (sanctum, api-key, jwt, etc.)
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $driver,
        public string $guard = 'api',
        public array $options = [],
    ) {}
}
