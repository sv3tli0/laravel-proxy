<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

use Closure;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\RouteDefinition;

class GroupBuilder
{
    protected string $prefix = '';

    protected ?string $domain = null;

    protected array $middleware = [];

    protected ?array $auth = null;

    protected ?string $tokenPayload = null;

    protected ?string $rateLimit = null;

    protected ?array $cors = null;

    protected ?array $logging = null;

    protected array $pipeline = [];

    /** @var array<RouteDefinition> */
    protected array $routes = [];

    /** @var array<RouteBuilder> */
    protected array $pendingRoutes = [];

    public function __construct(
        protected string $name,
    ) {}

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function domain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function middleware(string ...$middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    public function auth(string $driver, string $guard = 'api', array $options = []): static
    {
        $this->auth = ['driver' => $driver, 'guard' => $guard, ...$options];

        return $this;
    }

    public function tokenPayload(string $class): static
    {
        $this->tokenPayload = $class;

        return $this;
    }

    public function rateLimit(int $maxAttempts, int $decayMinutes = 1): static
    {
        $this->rateLimit = "{$maxAttempts},{$decayMinutes}";

        return $this;
    }

    public function cors(array $config): static
    {
        $this->cors = $config;

        return $this;
    }

    public function logging(string $level, ?float $samplingRate = null): static
    {
        $this->logging = ['level' => $level];

        if ($samplingRate !== null) {
            $this->logging['sampling'] = ['enabled' => true, 'rate' => $samplingRate];
        }

        return $this;
    }

    public function pipeline(string ...$stages): static
    {
        $this->pipeline = array_merge($this->pipeline, $stages);

        return $this;
    }

    public function addRoute(RouteBuilder $route): static
    {
        $this->pendingRoutes[] = $route;

        return $this;
    }

    /**
     * Define routes within this group using a closure.
     */
    public function routes(Closure $callback): static
    {
        $collector = new RouteCollector;
        $callback($collector);
        $this->pendingRoutes = array_merge($this->pendingRoutes, $collector->getRoutes());

        return $this;
    }

    public function build(): GroupDefinition
    {
        $routes = $this->routes;

        foreach ($this->pendingRoutes as $routeBuilder) {
            $routes[] = $routeBuilder->build();
        }

        return new GroupDefinition(
            name: $this->name,
            prefix: $this->prefix,
            domain: $this->domain,
            middleware: $this->middleware,
            auth: $this->auth,
            tokenPayload: $this->tokenPayload,
            rateLimit: $this->rateLimit,
            cors: $this->cors,
            logging: $this->logging,
            pipeline: $this->pipeline,
            routes: $routes,
        );
    }
}
