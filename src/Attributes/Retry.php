<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Retry
{
    /**
     * @param  int  $times  Number of retry attempts
     * @param  int  $delay  Initial delay in milliseconds
     * @param  int  $multiplier  Backoff multiplier
     * @param  array<int>  $on  HTTP status codes to retry on
     */
    public function __construct(
        public int $times = 3,
        public int $delay = 100,
        public int $multiplier = 2,
        public array $on = [500, 502, 503, 504],
    ) {}
}
