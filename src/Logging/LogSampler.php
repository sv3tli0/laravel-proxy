<?php

declare(strict_types=1);

namespace Lararoxy\Logging;

class LogSampler
{
    public function __construct(
        protected bool $enabled = false,
        protected float $rate = 1.0,
    ) {}

    /**
     * Determine if this request should be logged based on sampling rate.
     */
    public function shouldLog(): bool
    {
        if (! $this->enabled) {
            return true;
        }

        if ($this->rate >= 1.0) {
            return true;
        }

        if ($this->rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $this->rate;
    }
}
