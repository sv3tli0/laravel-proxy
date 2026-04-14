<?php

declare(strict_types=1);

namespace Lararoxy\Http;

use Illuminate\Support\Facades\Cache;
use Lararoxy\Data\CircuitBreakerConfig;
use Lararoxy\Enums\CircuitState;
use Lararoxy\Exceptions\CircuitOpenException;

class CircuitBreakerManager
{
    protected string $prefix = 'lararoxy:circuit:';

    /**
     * Check if a request is allowed through the circuit breaker.
     *
     * @throws CircuitOpenException
     */
    public function check(string $serviceName, CircuitBreakerConfig $config): void
    {
        if (! $config->enabled) {
            return;
        }

        $state = $this->getState($serviceName);

        if ($state === CircuitState::Open) {
            $openedAt = Cache::get($this->key($serviceName, 'opened_at'), 0);
            $elapsed = time() - $openedAt;

            if ($elapsed < $config->timeout) {
                throw new CircuitOpenException($serviceName, $config->timeout - $elapsed);
            }

            // Transition to half-open
            $this->setState($serviceName, CircuitState::HalfOpen);
        }
    }

    /**
     * Record a successful request.
     */
    public function recordSuccess(string $serviceName, CircuitBreakerConfig $config): void
    {
        if (! $config->enabled) {
            return;
        }

        $state = $this->getState($serviceName);

        if ($state === CircuitState::HalfOpen) {
            $this->setState($serviceName, CircuitState::Closed);
            Cache::forget($this->key($serviceName, 'failures'));
        }
    }

    /**
     * Record a failed request.
     */
    public function recordFailure(string $serviceName, CircuitBreakerConfig $config): void
    {
        if (! $config->enabled) {
            return;
        }

        $state = $this->getState($serviceName);

        if ($state === CircuitState::HalfOpen) {
            $this->open($serviceName);

            return;
        }

        $failures = Cache::increment($this->key($serviceName, 'failures'));

        if ($failures >= $config->threshold) {
            $this->open($serviceName);
        }
    }

    public function getState(string $serviceName): CircuitState
    {
        $state = Cache::get($this->key($serviceName, 'state'));

        return CircuitState::tryFrom($state ?? '') ?? CircuitState::Closed;
    }

    protected function open(string $serviceName): void
    {
        $this->setState($serviceName, CircuitState::Open);
        Cache::put($this->key($serviceName, 'opened_at'), time(), 3600);
    }

    protected function setState(string $serviceName, CircuitState $state): void
    {
        Cache::put($this->key($serviceName, 'state'), $state->value, 3600);
    }

    protected function key(string $serviceName, string $suffix): string
    {
        return $this->prefix.$serviceName.':'.$suffix;
    }
}
