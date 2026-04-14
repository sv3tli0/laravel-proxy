<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RateLimit
{
    public function __construct(
        public int $maxAttempts,
        public int $decayMinutes = 1,
    ) {}
}
