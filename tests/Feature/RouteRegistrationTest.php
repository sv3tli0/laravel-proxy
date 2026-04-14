<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Illuminate\Http\Request;
use Lararoxy\Data\CallbackConfig;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Http\RouteRegistrar;
use Lararoxy\Support\AttributeScanner;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;

class RouteRegistrationTest extends TestCase
{
    private function makeRegistry(): ConfigRegistry
    {
        $r = new ConfigRegistry(new AttributeScanner);
        $r->boot(['attributes' => ['enabled' => false]]);
        $r->registerService(new ServiceDefinition(name: 'svc', baseUrl: 'http://svc'));

        return $r;
    }

    public function test_registers_proxy_routes(): void
    {
        $registry = $this->makeRegistry();
        $registry->registerGroup(new GroupDefinition(
            name: 'api', prefix: '/proxy',
            routes: [new RouteDefinition(method: 'GET', path: '/users/{id}', upstream: 'svc', upstreamPath: '/users/{id}')],
        ));

        (new RouteRegistrar($registry))->register();

        $matched = app('router')->getRoutes()->match(Request::create('/proxy/users/42', 'GET'));
        $this->assertStringContainsString('lararoxy.api', $matched->getName());
    }

    public function test_registers_multiple_groups(): void
    {
        $registry = $this->makeRegistry();
        $registry->registerGroup(new GroupDefinition(name: 'v1', prefix: '/v1', routes: [
            new RouteDefinition(method: 'GET', path: '/health', upstream: 'svc', upstreamPath: '/h'),
        ]));
        $registry->registerGroup(new GroupDefinition(name: 'v2', prefix: '/v2', routes: [
            new RouteDefinition(method: 'POST', path: '/data', upstream: 'svc', upstreamPath: '/d'),
        ]));

        (new RouteRegistrar($registry))->register();

        $this->assertNotNull(app('router')->getRoutes()->match(Request::create('/v1/health', 'GET')));
        $this->assertNotNull(app('router')->getRoutes()->match(Request::create('/v2/data', 'POST')));
    }

    public function test_registers_wildcard_route(): void
    {
        $registry = $this->makeRegistry();
        $registry->registerGroup(new GroupDefinition(name: 'catch', prefix: '/catch', routes: [
            new RouteDefinition(method: 'GET', path: '/all', upstream: 'svc', upstreamPath: '/all', wildcard: true),
        ]));

        (new RouteRegistrar($registry))->register();

        $matched = app('router')->getRoutes()->match(Request::create('/catch/all', 'GET'));
        $this->assertNotNull($matched);
    }

    public function test_registers_webhook_routes(): void
    {
        $registry = $this->makeRegistry();
        $registry->registerOutgoing(new OutgoingServiceDefinition(
            name: 'pay', baseUrl: 'http://pay',
            callback: new CallbackConfig(path: '/hooks/pay/{tracking_id}'),
        ));

        (new RouteRegistrar($registry))->registerWebhooks();

        $matched = app('router')->getRoutes()->match(Request::create('/hooks/pay/trk_123', 'POST'));
        $this->assertNotNull($matched);
    }

    public function test_skips_outgoing_without_callback(): void
    {
        $registry = $this->makeRegistry();
        $registry->registerOutgoing(new OutgoingServiceDefinition(name: 'no-cb', baseUrl: 'http://no-cb'));

        $routesBefore = count(app('router')->getRoutes());
        (new RouteRegistrar($registry))->registerWebhooks();

        $this->assertSame($routesBefore, count(app('router')->getRoutes()));
    }

    public function test_group_without_prefix_or_middleware(): void
    {
        $registry = $this->makeRegistry();
        $registry->registerGroup(new GroupDefinition(name: 'bare', routes: [
            new RouteDefinition(method: 'GET', path: '/bare-test', upstream: 'svc', upstreamPath: '/test'),
        ]));

        (new RouteRegistrar($registry))->register();

        $this->assertNotNull(app('router')->getRoutes()->match(Request::create('/bare-test', 'GET')));
    }
}
