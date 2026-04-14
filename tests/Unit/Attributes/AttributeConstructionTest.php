<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Attributes;

use Lararoxy\Attributes\AggregateRoute;
use Lararoxy\Attributes\AggregateSource;
use Lararoxy\Attributes\Auth;
use Lararoxy\Attributes\Callback;
use Lararoxy\Attributes\CircuitBreaker;
use Lararoxy\Attributes\EndpointGroup;
use Lararoxy\Attributes\InjectHeader;
use Lararoxy\Attributes\Logging;
use Lararoxy\Attributes\OutgoingService;
use Lararoxy\Attributes\ProxyRoute;
use Lararoxy\Attributes\RateLimit;
use Lararoxy\Attributes\Retry;
use Lararoxy\Attributes\TokenField;
use Lararoxy\Attributes\Tracking;
use Lararoxy\Attributes\UpstreamService;
use Lararoxy\Tests\TestCase;
use ReflectionClass;

class AttributeConstructionTest extends TestCase
{
    public function test_endpoint_group_holds_all_config(): void
    {
        $attr = new EndpointGroup(
            name: 'api',
            prefix: '/api/v1',
            middleware: ['throttle:60,1'],
            tokenPayload: 'App\\Payloads\\User',
        );

        $this->assertSame('api', $attr->name);
        $this->assertSame('/api/v1', $attr->prefix);
        $this->assertSame(['throttle:60,1'], $attr->middleware);
        $this->assertSame('App\\Payloads\\User', $attr->tokenPayload);
        $this->assertNull($attr->domain);
    }

    public function test_upstream_service_defaults(): void
    {
        $attr = new UpstreamService(name: 'users', baseUrl: 'http://users-svc');

        $this->assertSame(30, $attr->timeout);
        $this->assertSame(5, $attr->connectTimeout);
    }

    public function test_proxy_route_is_repeatable(): void
    {
        $ref = new ReflectionClass(ProxyRoute::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $instance = $attrs[0]->newInstance();

        $this->assertTrue(($instance->flags & \Attribute::IS_REPEATABLE) !== 0);
        $this->assertTrue(($instance->flags & \Attribute::TARGET_METHOD) !== 0);
    }

    public function test_auth_attribute_with_options(): void
    {
        $attr = new Auth(driver: 'sanctum', guard: 'web', options: ['abilities' => ['read']]);

        $this->assertSame('sanctum', $attr->driver);
        $this->assertSame('web', $attr->guard);
        $this->assertSame(['abilities' => ['read']], $attr->options);
    }

    public function test_retry_defaults(): void
    {
        $attr = new Retry;

        $this->assertSame(3, $attr->times);
        $this->assertSame(100, $attr->delay);
        $this->assertSame(2, $attr->multiplier);
        $this->assertSame([500, 502, 503, 504], $attr->on);
    }

    public function test_circuit_breaker_custom_values(): void
    {
        $attr = new CircuitBreaker(threshold: 10, timeout: 60);

        $this->assertSame(10, $attr->threshold);
        $this->assertSame(60, $attr->timeout);
    }

    public function test_all_remaining_attributes_construct(): void
    {
        $agg = new AggregateRoute(method: 'GET', path: '/dash');
        $this->assertSame('GET', $agg->method);

        $src = new AggregateSource(upstream: 'svc', path: '/a', as: 'a');
        $this->assertSame('a', $src->as);

        $rl = new RateLimit(maxAttempts: 60, decayMinutes: 5);
        $this->assertSame(5, $rl->decayMinutes);

        $tf = new TokenField(from: 'model.id', resolver: 'App\\Resolver');
        $this->assertSame('App\\Resolver', $tf->resolver);

        $ih = new InjectHeader(name: 'X-User-Id', join: ',');
        $this->assertSame(',', $ih->join);

        $log = new Logging(level: 'full', samplingRate: 0.5);
        $this->assertSame(0.5, $log->samplingRate);

        $out = new OutgoingService(name: 'svc', baseUrl: 'http://svc', timeout: 60);
        $this->assertSame(60, $out->timeout);

        $trk = new Tracking(store: 'redis', ttl: 3600);
        $this->assertSame('redis', $trk->store);

        $cb = new Callback(path: '/hooks/{id}', handler: 'App\\Handler', signatureHeader: 'X-Sig');
        $this->assertSame('X-Sig', $cb->signatureHeader);
    }
}
