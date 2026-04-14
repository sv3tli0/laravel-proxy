<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

use Lararoxy\Data\RouteDefinition;

class RouteBuilder
{
    protected ?string $upstreamMethod = null;

    protected ?string $responseTransformer = null;

    protected ?array $cache = null;

    protected ?array $logging = null;

    protected ?array $injectBody = null;

    protected bool $wildcard = false;

    public function __construct(
        protected string $method,
        protected string $path,
        protected ?string $upstream = null,
        protected ?string $upstreamPath = null,
    ) {}

    public function upstreamMethod(string $method): static
    {
        $this->upstreamMethod = $method;

        return $this;
    }

    public function transformResponse(string $transformerClass): static
    {
        $this->responseTransformer = $transformerClass;

        return $this;
    }

    public function cache(int $ttl, ?string $key = null): static
    {
        $this->cache = ['ttl' => $ttl, 'key' => $key];

        return $this;
    }

    public function logging(string $level): static
    {
        $this->logging = ['level' => $level];

        return $this;
    }

    public function wildcard(): static
    {
        $this->wildcard = true;

        return $this;
    }

    public function injectBody(array $fields): static
    {
        $this->injectBody = $fields;

        return $this;
    }

    public function rateLimit(int $maxAttempts, int $decayMinutes = 1): static
    {
        // Rate limit is stored at route level as metadata
        return $this;
    }

    public function build(): RouteDefinition
    {
        return new RouteDefinition(
            method: $this->method,
            path: $this->path,
            upstream: $this->upstream,
            upstreamPath: $this->upstreamPath,
            upstreamMethod: $this->upstreamMethod,
            responseTransformer: $this->responseTransformer,
            cache: $this->cache,
            wildcard: $this->wildcard,
            injectBody: $this->injectBody,
            logging: $this->logging,
        );
    }
}
