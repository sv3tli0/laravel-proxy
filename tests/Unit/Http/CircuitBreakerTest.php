<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Http;

use Lararoxy\Data\CircuitBreakerConfig;
use Lararoxy\Enums\CircuitState;
use Lararoxy\Exceptions\CircuitOpenException;
use Lararoxy\Http\CircuitBreakerManager;
use Lararoxy\Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreakerConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new CircuitBreakerConfig(enabled: true, threshold: 2, timeout: 30);
    }

    public function test_starts_closed(): void
    {
        $cb = new CircuitBreakerManager;
        $this->assertSame(CircuitState::Closed, $cb->getState('svc'));
    }

    public function test_opens_after_threshold_failures(): void
    {
        $cb = new CircuitBreakerManager;

        $cb->recordFailure('svc', $this->config);
        $this->assertSame(CircuitState::Closed, $cb->getState('svc'));

        $cb->recordFailure('svc', $this->config);
        $this->assertSame(CircuitState::Open, $cb->getState('svc'));
    }

    public function test_throws_when_open(): void
    {
        $cb = new CircuitBreakerManager;
        $config = new CircuitBreakerConfig(enabled: true, threshold: 1, timeout: 60);

        $cb->recordFailure('svc', $config);

        $this->expectException(CircuitOpenException::class);
        $cb->check('svc', $config);
    }

    public function test_transitions_to_half_open_after_timeout(): void
    {
        $cb = new CircuitBreakerManager;
        $config = new CircuitBreakerConfig(enabled: true, threshold: 1, timeout: 0);

        $cb->recordFailure('svc', $config); // opens
        $cb->check('svc', $config); // timeout=0 → half-open

        $this->assertSame(CircuitState::HalfOpen, $cb->getState('svc'));
    }

    public function test_success_in_half_open_closes_circuit(): void
    {
        $cb = new CircuitBreakerManager;
        $config = new CircuitBreakerConfig(enabled: true, threshold: 1, timeout: 0);

        $cb->recordFailure('svc', $config);
        $cb->check('svc', $config); // half-open
        $cb->recordSuccess('svc', $config);

        $this->assertSame(CircuitState::Closed, $cb->getState('svc'));
    }

    public function test_failure_in_half_open_reopens_circuit(): void
    {
        $cb = new CircuitBreakerManager;
        $config = new CircuitBreakerConfig(enabled: true, threshold: 1, timeout: 0);

        $cb->recordFailure('svc', $config);
        $cb->check('svc', $config); // half-open
        $cb->recordFailure('svc', $config);

        $this->assertSame(CircuitState::Open, $cb->getState('svc'));
    }

    public function test_disabled_circuit_breaker_does_nothing(): void
    {
        $cb = new CircuitBreakerManager;
        $disabled = new CircuitBreakerConfig(enabled: false);

        $cb->check('svc', $disabled);
        $cb->recordFailure('svc', $disabled);
        $cb->recordSuccess('svc', $disabled);

        $this->assertSame(CircuitState::Closed, $cb->getState('svc'));
    }
}
