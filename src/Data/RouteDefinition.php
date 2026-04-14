<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class RouteDefinition
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $upstream = null,
        public ?string $upstreamPath = null,
        public ?string $upstreamMethod = null,
        public ?array $aggregate = null,
        public ?string $responseTransformer = null,
        public ?array $cache = null,
        public bool $wildcard = false,
        public ?array $injectBody = null,
        public ?array $logging = null,
    ) {}

    public static function fromArray(array $config): static
    {
        return new self(
            method: $config['method'],
            path: $config['path'],
            upstream: $config['upstream'] ?? null,
            upstreamPath: $config['upstream_path'] ?? null,
            upstreamMethod: $config['upstream_method'] ?? null,
            aggregate: $config['aggregate'] ?? null,
            responseTransformer: $config['response_transformer'] ?? null,
            cache: $config['cache'] ?? null,
            wildcard: $config['wildcard'] ?? false,
            injectBody: $config['inject_body'] ?? null,
            logging: $config['logging'] ?? null,
        );
    }
}
