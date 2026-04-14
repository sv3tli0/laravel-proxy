<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

interface TrackingIdGenerator
{
    /**
     * Generate a unique tracking ID.
     */
    public function generate(string $prefix = 'trk_'): string;
}
