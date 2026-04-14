<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Contracts\TokenPayload;
use Lararoxy\Data\ProxyContext;

class RequestTransformStage implements ProxyPipelineStage
{
    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        // Resolve upstream path with variable substitution
        $context->resolvedUpstreamPath = $this->resolvePath(
            $context->route->upstreamPath ?? $context->route->path,
            $context->request->route()?->parameters() ?? [],
            $context->tokenPayload,
        );

        // Resolve upstream method (default to original request method)
        $context->resolvedUpstreamMethod = $context->route->upstreamMethod
            ?? $context->route->method;

        // Inject body fields if configured
        if ($context->route->injectBody !== null) {
            $context->request->merge($context->route->injectBody);
        }

        $context->addTrace('request_transform', (microtime(true) - $start) * 1000);

        return $next($context);
    }

    /**
     * Resolve path variables from route params and token payload.
     *
     * Supports: {id}, {slug} from route params
     *           {token.user_id}, {token.tenant_id} from TokenPayload
     */
    protected function resolvePath(string $path, array $routeParams, ?object $payload): string
    {
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($routeParams, $payload) {
            $key = $matches[1];

            // Token payload variables: {token.user_id}
            if (str_starts_with($key, 'token.') && $payload instanceof TokenPayload) {
                $field = substr($key, 6);

                return (string) $payload->resolve($field);
            }

            // Route parameters: {id}, {slug}
            return (string) ($routeParams[$key] ?? $matches[0]);
        }, $path) ?? $path;
    }
}
