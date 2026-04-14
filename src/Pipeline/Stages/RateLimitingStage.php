<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline\Stages;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Response;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Data\ProxyContext;

class RateLimitingStage implements ProxyPipelineStage
{
    public function __construct(
        protected RateLimiter $limiter,
    ) {}

    public function handle(ProxyContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $rateLimit = $context->group->rateLimit;

        if ($rateLimit === null) {
            $context->addTrace('rate_limiting', (microtime(true) - $start) * 1000);

            return $next($context);
        }

        [$maxAttempts, $decayMinutes] = $this->parseRateLimit($rateLimit);
        $key = 'lararoxy:'.$context->group->name.':'.$context->request->ip();

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $context->addTrace('rate_limiting', (microtime(true) - $start) * 1000);

            return new Response(
                json_encode(['error' => 'Too Many Requests']),
                429,
                [
                    'Content-Type' => 'application/json',
                    'Retry-After' => (string) $this->limiter->availableIn($key),
                ],
            );
        }

        $this->limiter->hit($key, $decayMinutes * 60);
        $context->addTrace('rate_limiting', (microtime(true) - $start) * 1000);

        return $next($context);
    }

    /**
     * @return array{int, int}
     */
    protected function parseRateLimit(string $rateLimit): array
    {
        $parts = explode(',', $rateLimit);

        return [
            (int) ($parts[0] ?? 60),
            (int) ($parts[1] ?? 1),
        ];
    }
}
