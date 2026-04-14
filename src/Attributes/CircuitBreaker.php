<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CircuitBreaker
{
    /**
     * @param  int  $threshold  Failure count before opening
     * @param  int  $timeout  Seconds before half-open
     */
    public function __construct(
        public int $threshold = 5,
        public int $timeout = 30,
    ) {}
}
