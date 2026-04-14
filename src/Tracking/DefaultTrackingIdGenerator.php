<?php

declare(strict_types=1);

namespace Lararoxy\Tracking;

use Illuminate\Support\Str;
use Lararoxy\Contracts\TrackingIdGenerator;

class DefaultTrackingIdGenerator implements TrackingIdGenerator
{
    public function generate(string $prefix = 'trk_'): string
    {
        return $prefix.Str::ulid()->toBase32();
    }
}
