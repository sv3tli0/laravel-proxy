<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Data;

use Illuminate\Http\Request;
use Lararoxy\Data\CallbackConfig;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\HealthCheckConfig;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\ProxyContext;
use Lararoxy\Data\RetryConfig;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Data\TrackingConfig;
use Lararoxy\Tests\TestCase;

class DataTransferObjectTest extends TestCase
{
    public function test_service_definition_from_array_full(): void
    {
        $svc = ServiceDefinition::fromArray('users', [
            'base_url' => 'http://users-svc',
            'timeout' => 15,
            'connect_timeout' => 3,
            'retry' => ['times' => 2, 'delay' => 200],
            'circuit_breaker' => ['enabled' => true, 'threshold' => 3],
        ]);

        $this->assertSame('users', $svc->name);
        $this->assertSame(15, $svc->timeout);
        $this->assertInstanceOf(RetryConfig::class, $svc->retry);
        $this->assertTrue($svc->circuitBreaker->enabled);
    }

    public function test_service_definition_from_array_minimal(): void
    {
        $svc = ServiceDefinition::fromArray('simple', ['base_url' => 'http://simple']);

        $this->assertSame(30, $svc->timeout);
        $this->assertNull($svc->retry);
        $this->assertNull($svc->circuitBreaker);
    }

    public function test_group_definition_from_array_with_routes(): void
    {
        $group = GroupDefinition::fromArray('api', [
            'prefix' => '/api/v1',
            'auth' => ['driver' => 'sanctum'],
            'routes' => [
                ['method' => 'GET', 'path' => '/users', 'upstream' => 'users', 'upstream_path' => '/users'],
            ],
        ]);

        $this->assertSame('sanctum', $group->auth['driver']);
        $this->assertCount(1, $group->routes);
        $this->assertInstanceOf(RouteDefinition::class, $group->routes[0]);
    }

    public function test_route_definition_from_array(): void
    {
        $route = RouteDefinition::fromArray([
            'method' => 'POST',
            'path' => '/me/orders',
            'upstream' => 'orders',
            'upstream_path' => '/api/users/{token.user_id}/orders',
            'upstream_method' => 'PUT',
        ]);

        $this->assertSame('PUT', $route->upstreamMethod);
        $this->assertFalse($route->wildcard);
    }

    public function test_outgoing_definition_from_array(): void
    {
        $out = OutgoingServiceDefinition::fromArray('pay', [
            'base_url' => 'http://pay',
            'tracking' => ['enabled' => true, 'store' => 'database', 'ttl' => 3600],
            'callback' => ['path' => '/hooks/{id}', 'handler' => 'App\\Handler'],
        ]);

        $this->assertInstanceOf(TrackingConfig::class, $out->tracking);
        $this->assertSame(3600, $out->tracking->ttl);
        $this->assertInstanceOf(CallbackConfig::class, $out->callback);
    }

    public function test_retry_config_defaults(): void
    {
        $retry = new RetryConfig;
        $this->assertSame(3, $retry->times);
        $this->assertSame([500, 502, 503, 504], $retry->on);
    }

    public function test_health_check_config_from_array(): void
    {
        $hc = HealthCheckConfig::fromArray(['path' => '/status', 'interval' => 60]);
        $this->assertSame('/status', $hc->path);
        $this->assertSame(60, $hc->interval);
    }

    public function test_proxy_context_add_trace(): void
    {
        $context = new ProxyContext(
            request: Request::create('/test'),
            group: new GroupDefinition(name: 'api'),
            route: new RouteDefinition(method: 'GET', path: '/test'),
        );

        $context->addTrace('auth', 1.5);
        $context->addTrace('dispatch', 42.3);

        $this->assertCount(2, $context->pipelineTrace);
        $this->assertSame('auth', $context->pipelineTrace[0]['stage']);
        $this->assertSame(1.5, $context->pipelineTrace[0]['duration_ms']);
    }
}
