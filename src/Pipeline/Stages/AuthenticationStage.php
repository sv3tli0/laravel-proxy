<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Illuminate\Http\Response;
use Lararoxy\Auth\AuthDriverFactory;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Data\ProxyContext;

class AuthenticationStage implements ProxyPipelineStage
{
    public function __construct(
        protected AuthDriverFactory $factory,
    ) {}

    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $authConfig = $context->group->auth;

        if ($authConfig === null) {
            $context->addTrace('authentication', (microtime(true) - $start) * 1000);

            return $next($context);
        }

        $driver = $this->factory->make($authConfig);
        $model = $driver->authenticate($context->request);

        if ($model === null && ($authConfig['driver'] ?? '') !== 'passthrough') {
            $context->addTrace('authentication', (microtime(true) - $start) * 1000);

            return new Response(
                json_encode(['error' => 'Unauthenticated']),
                401,
                ['Content-Type' => 'application/json'],
            );
        }

        $context->authModel = $model;
        $context->addTrace('authentication', (microtime(true) - $start) * 1000);

        return $next($context);
    }
}
