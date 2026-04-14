<?php

declare(strict_types=1);

namespace Lararoxy\Data;

final readonly class GroupDefinition
{
    public function __construct(
        public string $name,
        public string $prefix = '',
        public ?string $domain = null,
        public array $middleware = [],
        public ?array $auth = null,
        public ?string $tokenPayload = null,
        public ?string $rateLimit = null,
        public ?array $cors = null,
        public ?array $logging = null,
        public array $pipeline = [],
        public array $routes = [],
    ) {}

    public static function fromArray(string $name, array $config): static
    {
        return new self(
            name: $name,
            prefix: $config['prefix'] ?? '',
            domain: $config['domain'] ?? null,
            middleware: $config['middleware'] ?? [],
            auth: $config['auth'] ?? null,
            tokenPayload: $config['token_payload'] ?? null,
            rateLimit: $config['rate_limit'] ?? null,
            cors: $config['cors'] ?? null,
            logging: $config['logging'] ?? null,
            pipeline: $config['pipeline'] ?? [],
            routes: array_map(
                fn (array $route) => RouteDefinition::fromArray($route),
                $config['routes'] ?? [],
            ),
        );
    }
}
