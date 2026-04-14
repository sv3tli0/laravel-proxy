<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class InjectHeader
{
    /**
     * @param  string  $name  Header name to inject (e.g., 'X-User-Id')
     * @param  string|null  $join  Glue for array values (e.g., ',')
     */
    public function __construct(
        public string $name,
        public ?string $join = null,
    ) {}
}
