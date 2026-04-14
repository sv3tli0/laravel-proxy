<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Data\ProxyContext;
use Lararoxy\Enums\LogLevel;
use Lararoxy\Logging\RequestLogger;

class PostLoggingStage implements ProxyPipelineStage
{
    public function __construct(
        protected RequestLogger $logger,
    ) {}

    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $level = $this->resolveLevel($context);
        $statusCode = $context->upstreamResponse?->status();

        $effectiveLevel = $this->logger->resolveLevel($level, $statusCode);
        $this->logger->logRequest($context, $effectiveLevel);

        $context->addTrace('post_logging', (microtime(true) - $start) * 1000);

        return $next($context);
    }

    protected function resolveLevel(ProxyContext $context): LogLevel
    {
        // Route-level override > Group-level > Global
        $routeLevel = $context->route->logging['level'] ?? null;
        $groupLevel = $context->group->logging['level'] ?? null;
        $globalLevel = config('lararoxy.logging.default_level', 'minimal');

        $levelString = $routeLevel ?? $groupLevel ?? $globalLevel;

        return LogLevel::tryFrom($levelString) ?? LogLevel::Minimal;
    }
}
