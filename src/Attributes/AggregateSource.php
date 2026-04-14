<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AggregateSource
{
    /**
     * @param  string  $upstream  Upstream service name
     * @param  string  $path  Upstream path
     * @param  string  $as  Key in merged response
     */
    public function __construct(
        public string $upstream,
        public string $path,
        public string $as,
    ) {}
}
