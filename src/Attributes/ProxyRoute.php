<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ProxyRoute
{
    /**
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  string  $path  External path
     * @param  string  $upstream  Upstream service name
     * @param  string  $upstreamPath  Internal upstream path (supports {token.*} variables)
     * @param  string|null  $upstreamMethod  Override HTTP method for upstream
     * @param  bool  $wildcard  Catch-all forwarding
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $upstream,
        public string $upstreamPath,
        public ?string $upstreamMethod = null,
        public bool $wildcard = false,
    ) {}
}
