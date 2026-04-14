<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Illuminate\Http\Response;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Data\ProxyContext;
use Lararoxy\Exceptions\CircuitOpenException;
use Lararoxy\Exceptions\UpstreamException;
use Lararoxy\Http\ProxyHttpClient;
use Lararoxy\Support\ConfigRegistry;

class UpstreamDispatchStage implements ProxyPipelineStage
{
    public function __construct(
        protected ProxyHttpClient $client,
        protected ConfigRegistry $registry,
    ) {}

    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $upstream = $context->route->upstream;

        if ($upstream === null) {
            $context->addTrace('upstream_dispatch', (microtime(true) - $start) * 1000);

            return new Response(
                json_encode(['error' => 'No upstream configured']),
                502,
                ['Content-Type' => 'application/json'],
            );
        }

        try {
            $service = $this->registry->getService($upstream);

            $response = $this->client->send(
                service: $service,
                method: $context->resolvedUpstreamMethod ?? $context->request->method(),
                path: $context->resolvedUpstreamPath ?? $context->route->path,
                headers: $context->upstreamHeaders,
                body: $this->getRequestBody($context),
            );

            $context->upstreamResponse = $response;
        } catch (CircuitOpenException $e) {
            $context->addTrace('upstream_dispatch', (microtime(true) - $start) * 1000);

            return new Response(
                json_encode(['error' => 'Service Unavailable', 'retry_after' => $e->retryAfter]),
                503,
                ['Content-Type' => 'application/json', 'Retry-After' => (string) $e->retryAfter],
            );
        } catch (UpstreamException $e) {
            $context->addTrace('upstream_dispatch', (microtime(true) - $start) * 1000);

            return new Response(
                json_encode(['error' => 'Bad Gateway']),
                502,
                ['Content-Type' => 'application/json'],
            );
        }

        $context->addTrace('upstream_dispatch', (microtime(true) - $start) * 1000);

        return $next($context);
    }

    protected function getRequestBody(ProxyContext $context): mixed
    {
        $method = $context->resolvedUpstreamMethod ?? $context->request->method();

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $content = $context->request->getContent();

        if ($content !== '') {
            $decoded = json_decode($content, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
        }

        return $context->request->all() ?: null;
    }
}
