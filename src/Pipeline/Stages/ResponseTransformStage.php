<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Contracts\ResponseTransformer;
use Lararoxy\Data\ProxyContext;

class ResponseTransformStage implements ProxyPipelineStage
{
    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $transformerClass = $context->route->responseTransformer;

        if ($transformerClass !== null && class_exists($transformerClass)) {
            $transformer = app($transformerClass);

            if ($transformer instanceof ResponseTransformer) {
                $context->upstreamResponse = $transformer->transform(
                    $context->upstreamResponse,
                    $context->request,
                );
            }
        }

        $context->addTrace('response_transform', (microtime(true) - $start) * 1000);

        return $next($context);
    }
}
