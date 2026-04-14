<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Illuminate\Http\Response;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Contracts\TokenPayload;
use Lararoxy\Data\ProxyContext;

class AuthorizationStage implements ProxyPipelineStage
{
    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $payload = $context->tokenPayload;

        if ($payload instanceof TokenPayload) {
            $routeName = $context->group->name.'.'.$context->route->path;
            $routeParams = $context->request->route()?->parameters() ?? [];

            if (! $payload->authorize($routeName, $routeParams)) {
                $context->addTrace('authorization', (microtime(true) - $start) * 1000);

                return new Response(
                    json_encode(['error' => 'Forbidden']),
                    403,
                    ['Content-Type' => 'application/json'],
                );
            }
        }

        $context->addTrace('authorization', (microtime(true) - $start) * 1000);

        return $next($context);
    }
}
