<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Illuminate\Support\Str;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Data\ProxyContext;

class PreLoggingStage implements ProxyPipelineStage
{
    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        // Assign request ID if not already set
        if ($context->requestId === null) {
            $trustIncoming = config('lararoxy.request_id.trust_incoming', false);
            $header = config('lararoxy.request_id.header', 'X-Request-Id');

            if ($trustIncoming && $context->request->hasHeader($header)) {
                $context->requestId = $context->request->header($header);
            } else {
                $context->requestId = Str::ulid()->toBase32();
            }
        }

        // Forward request ID to upstream
        if (config('lararoxy.request_id.forward', true)) {
            $header = config('lararoxy.request_id.header', 'X-Request-Id');
            $context->upstreamHeaders[$header] = $context->requestId;
        }

        $context->addTrace('pre_logging', (microtime(true) - $start) * 1000);

        return $next($context);
    }
}
