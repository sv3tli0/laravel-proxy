<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Tracking
{
    /**
     * @param  string  $store  Store driver: database, redis, cache
     * @param  int  $ttl  Time to live in seconds
     * @param  string  $idHeader  Header name for tracking ID
     */
    public function __construct(
        public string $store = 'database',
        public int $ttl = 86400,
        public string $idHeader = 'X-Tracking-Id',
    ) {}
}
