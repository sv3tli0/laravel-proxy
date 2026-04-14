<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

use Lararoxy\Data\RouteDefinition;

class AggregateBuilder
{
    protected array $sources = [];

    protected ?string $responseTransformer = null;

    protected string $method = 'GET';

    public function __construct(
        protected string $path,
    ) {}

    public function from(string $upstream, string $path, string $as): static
    {
        $this->sources[] = [
            'upstream' => $upstream,
            'path' => $path,
            'as' => $as,
        ];

        return $this;
    }

    public function transformResponse(string $transformerClass): static
    {
        $this->responseTransformer = $transformerClass;

        return $this;
    }

    public function method(string $method): static
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function build(): RouteDefinition
    {
        return new RouteDefinition(
            method: $this->method,
            path: $this->path,
            aggregate: $this->sources,
            responseTransformer: $this->responseTransformer,
        );
    }
}
