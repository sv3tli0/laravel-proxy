<?php

declare(strict_types=1);

namespace Lararoxy\Support;

use Lararoxy\Attributes\AggregateRoute;
use Lararoxy\Attributes\AggregateSource;
use Lararoxy\Attributes\Auth;
use Lararoxy\Attributes\Callback;
use Lararoxy\Attributes\CircuitBreaker;
use Lararoxy\Attributes\EndpointGroup;
use Lararoxy\Attributes\Logging;
use Lararoxy\Attributes\OutgoingService;
use Lararoxy\Attributes\ProxyRoute;
use Lararoxy\Attributes\RateLimit;
use Lararoxy\Attributes\Retry;
use Lararoxy\Attributes\Tracking;
use Lararoxy\Attributes\UpstreamService;
use Lararoxy\Data\CallbackConfig;
use Lararoxy\Data\CircuitBreakerConfig;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\RetryConfig;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Data\TrackingConfig;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class AttributeScanner
{
    /** @var array<string, ServiceDefinition> */
    protected array $services = [];

    /** @var array<string, GroupDefinition> */
    protected array $groups = [];

    /** @var array<string, OutgoingServiceDefinition> */
    protected array $outgoing = [];

    /**
     * Scan the given directories for attribute-annotated classes.
     *
     * @param  array<string>  $paths
     */
    public function scan(array $paths): void
    {
        $paths = array_filter($paths, fn (string $path) => is_dir($path));

        if (empty($paths)) {
            return;
        }

        $finder = Finder::create()
            ->files()
            ->name('*.php')
            ->in($paths);

        foreach ($finder as $file) {
            $class = $this->resolveClassFromFile($file->getRealPath());

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            $this->scanUpstreamService($reflection);
            $this->scanEndpointGroup($reflection);
            $this->scanOutgoingService($reflection);
        }
    }

    /** @return array<string, ServiceDefinition> */
    public function getServices(): array
    {
        return $this->services;
    }

    /** @return array<string, GroupDefinition> */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /** @return array<string, OutgoingServiceDefinition> */
    public function getOutgoing(): array
    {
        return $this->outgoing;
    }

    protected function scanUpstreamService(ReflectionClass $reflection): void
    {
        $attrs = $reflection->getAttributes(UpstreamService::class);

        if (empty($attrs)) {
            return;
        }

        $service = $attrs[0]->newInstance();

        $retry = $this->getClassAttribute($reflection, Retry::class);
        $cb = $this->getClassAttribute($reflection, CircuitBreaker::class);

        $this->services[$service->name] = new ServiceDefinition(
            name: $service->name,
            baseUrl: $service->baseUrl,
            timeout: $service->timeout,
            connectTimeout: $service->connectTimeout,
            retry: $retry ? new RetryConfig(
                times: $retry->times,
                delay: $retry->delay,
                multiplier: $retry->multiplier,
                on: $retry->on,
            ) : null,
            circuitBreaker: $cb ? new CircuitBreakerConfig(
                enabled: true,
                threshold: $cb->threshold,
                timeout: $cb->timeout,
            ) : null,
        );
    }

    protected function scanEndpointGroup(ReflectionClass $reflection): void
    {
        $attrs = $reflection->getAttributes(EndpointGroup::class);

        if (empty($attrs)) {
            return;
        }

        $group = $attrs[0]->newInstance();
        $auth = $this->getClassAttribute($reflection, Auth::class);
        $rateLimit = $this->getClassAttribute($reflection, RateLimit::class);
        $logging = $this->getClassAttribute($reflection, Logging::class);

        $routes = $this->scanRoutes($reflection);

        $this->groups[$group->name] = new GroupDefinition(
            name: $group->name,
            prefix: $group->prefix,
            domain: $group->domain,
            middleware: $group->middleware,
            auth: $auth ? [
                'driver' => $auth->driver,
                'guard' => $auth->guard,
                ...$auth->options,
            ] : null,
            tokenPayload: $group->tokenPayload,
            rateLimit: $rateLimit ? "{$rateLimit->maxAttempts},{$rateLimit->decayMinutes}" : null,
            logging: $logging ? [
                'level' => $logging->level,
                'sampling' => $logging->samplingRate !== null
                    ? ['enabled' => true, 'rate' => $logging->samplingRate]
                    : null,
            ] : null,
            routes: $routes,
        );
    }

    protected function scanOutgoingService(ReflectionClass $reflection): void
    {
        $attrs = $reflection->getAttributes(OutgoingService::class);

        if (empty($attrs)) {
            return;
        }

        $service = $attrs[0]->newInstance();
        $retry = $this->getClassAttribute($reflection, Retry::class);
        $tracking = $this->getClassAttribute($reflection, Tracking::class);
        $callback = $this->getClassAttribute($reflection, Callback::class);

        $this->outgoing[$service->name] = new OutgoingServiceDefinition(
            name: $service->name,
            baseUrl: $service->baseUrl,
            timeout: $service->timeout,
            retry: $retry ? new RetryConfig(
                times: $retry->times,
                delay: $retry->delay,
                multiplier: $retry->multiplier,
                on: $retry->on,
            ) : null,
            tracking: $tracking ? new TrackingConfig(
                store: $tracking->store,
                ttl: $tracking->ttl,
                idHeader: $tracking->idHeader,
            ) : null,
            callback: $callback ? new CallbackConfig(
                path: $callback->path,
                handler: $callback->handler,
                signatureHeader: $callback->signatureHeader,
                signatureVerifier: $callback->signatureVerifier,
            ) : null,
        );
    }

    /**
     * Scan methods for ProxyRoute and AggregateRoute attributes.
     *
     * @return array<RouteDefinition>
     */
    protected function scanRoutes(ReflectionClass $reflection): array
    {
        $routes = [];

        foreach ($reflection->getMethods() as $method) {
            // Direct/rewrite proxy routes
            foreach ($method->getAttributes(ProxyRoute::class) as $attr) {
                $route = $attr->newInstance();
                $methodLogging = $this->getMethodAttribute($method, Logging::class);
                $methodRateLimit = $this->getMethodAttribute($method, RateLimit::class);

                $logging = $methodLogging ? [
                    'level' => $methodLogging->level,
                    'sampling' => $methodLogging->samplingRate !== null
                        ? ['enabled' => true, 'rate' => $methodLogging->samplingRate]
                        : null,
                ] : null;

                $routes[] = new RouteDefinition(
                    method: $route->method,
                    path: $route->path,
                    upstream: $route->upstream,
                    upstreamPath: $route->upstreamPath,
                    upstreamMethod: $route->upstreamMethod,
                    wildcard: $route->wildcard,
                    logging: $logging,
                );
            }

            // Aggregation routes
            $aggregateAttrs = $method->getAttributes(AggregateRoute::class);

            if (! empty($aggregateAttrs)) {
                $agg = $aggregateAttrs[0]->newInstance();
                $sources = [];

                foreach ($method->getAttributes(AggregateSource::class) as $srcAttr) {
                    $src = $srcAttr->newInstance();
                    $sources[] = [
                        'upstream' => $src->upstream,
                        'path' => $src->path,
                        'as' => $src->as,
                    ];
                }

                $routes[] = new RouteDefinition(
                    method: $agg->method,
                    path: $agg->path,
                    aggregate: $sources,
                    responseTransformer: $agg->responseTransformer,
                );
            }
        }

        return $routes;
    }

    /**
     * Resolve the FQCN from a PHP file using token parsing.
     */
    protected function resolveClassFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;\s]+)\s*;/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/(?:class|enum)\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($namespace === null || $class === null) {
            return null;
        }

        return $namespace.'\\'.$class;
    }

    /**
     * Get a single class-level attribute instance.
     *
     * @template T of object
     *
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    protected function getClassAttribute(ReflectionClass $reflection, string $attributeClass): ?object
    {
        $attrs = $reflection->getAttributes($attributeClass);

        return ! empty($attrs) ? $attrs[0]->newInstance() : null;
    }

    /**
     * Get a single method-level attribute instance.
     *
     * @template T of object
     *
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    protected function getMethodAttribute(\ReflectionMethod $method, string $attributeClass): ?object
    {
        $attrs = $method->getAttributes($attributeClass);

        return ! empty($attrs) ? $attrs[0]->newInstance() : null;
    }
}
