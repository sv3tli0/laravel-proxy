<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class OutgoingService
{
    /**
     * @param  string  $name  Service identifier
     * @param  string  $baseUrl  Base URL or env var name
     * @param  int  $timeout  Request timeout in seconds
     */
    public function __construct(
        public string $name,
        public string $baseUrl,
        public int $timeout = 30,
    ) {}
}
