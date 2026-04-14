<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TokenField
{
    /**
     * @param  string  $from  Source path (e.g., 'model.id', 'model.tenant_id')
     * @param  string|null  $resolver  FQCN of custom resolver class
     */
    public function __construct(
        public string $from,
        public ?string $resolver = null,
    ) {}
}
