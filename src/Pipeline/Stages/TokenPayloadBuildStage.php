<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Lararoxy\Contracts\AuthModel;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Contracts\TokenPayload;
use Lararoxy\Data\ProxyContext;

class TokenPayloadBuildStage implements ProxyPipelineStage
{
    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $model = $context->authModel;

        if ($model instanceof AuthModel) {
            $payloadClass = $context->group->tokenPayload ?? $model->tokenPayloadClass();

            if (class_exists($payloadClass) && is_subclass_of($payloadClass, TokenPayload::class)) {
                $token = method_exists($model, 'currentAccessToken')
                    ? $model->currentAccessToken()
                    : null;

                $context->tokenPayload = $payloadClass::fromAuth($model, $token);
                $context->upstreamHeaders = array_merge(
                    $context->upstreamHeaders,
                    $context->tokenPayload->upstreamHeaders(),
                );
            }
        }

        $context->addTrace('token_payload_build', (microtime(true) - $start) * 1000);

        return $next($context);
    }
}
