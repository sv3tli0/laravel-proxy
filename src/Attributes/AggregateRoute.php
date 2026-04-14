<?php

declare(strict_types=1);

namespace Lararoxy\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class AggregateRoute
{
    /**
     * @param  string  $method  HTTP method
     * @param  string  $path  External path
     * @param  string|null  $responseTransformer  FQCN of ResponseTransformer
     */
    public function __construct(
        public string $method,
        public string $path,
        public ?string $responseTransformer = null,
    ) {}
}
