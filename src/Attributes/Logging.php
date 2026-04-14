<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Logging
{
    /**
     * @param  string  $level  Log level: none, minimal, standard, full, debug
     * @param  float|null  $samplingRate  Sampling rate (0.0 to 1.0)
     */
    public function __construct(
        public string $level = 'minimal',
        public ?float $samplingRate = null,
    ) {}
}
