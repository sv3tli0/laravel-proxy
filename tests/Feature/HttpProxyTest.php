<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Http\RouteRegistrar;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;
use Orchestra\Testbench\Attributes\WithConfig;

class HttpProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a test upstream service + group + routes
        $registry = $this->app->make(ConfigRegistry::class);

        $registry->registerService(new ServiceDefinition(
            name: 'backend',
            baseUrl: 'http://backend-svc',
        ));

        $registry->registerGroup(new GroupDefinition(
            name: 'api',
            prefix: '/proxy',
            routes: [
                new RouteDefinition(method: 'GET', path: '/users/{id}', upstream: 'backend', upstreamPath: '/api/users/{id}'),
                new RouteDefinition(method: 'POST', path: '/orders', upstream: 'backend', upstreamPath: '/api/orders'),
                new RouteDefinition(method: 'GET', path: '/health', upstream: 'backend', upstreamPath: '/health'),
            ],
        ));

        // Register routes into Laravel's router
        $registrar = new RouteRegistrar($registry);
        $registrar->register();
    }

    public function test_proxy_get_request_forwards_to_upstream(): void
    {
        Http::fake([
            'http://backend-svc/*' => Http::response(['id' => 42, 'name' => 'John'], 200),
        ]);

        $response = $this->get('/proxy/users/42');

        $response->assertStatus(200);
        $response->assertJson(['id' => 42, 'name' => 'John']);
    }

    public function test_proxy_post_request_forwards_to_upstream(): void
    {
        Http::fake([
            'http://backend-svc/*' => Http::response(['id' => 1, 'status' => 'created'], 201),
        ]);

        $response = $this->postJson('/proxy/orders', ['item' => 'widget', 'qty' => 3]);

        $response->assertStatus(201);
        $response->assertJson(['status' => 'created']);
    }

    public function test_proxy_returns_502_when_upstream_unreachable(): void
    {
        Http::fake([
            'http://backend-svc/*' => Http::response('', 502),
        ]);

        $response = $this->get('/proxy/health');

        $response->assertStatus(502);
    }

    public function test_proxy_assigns_request_id_header(): void
    {
        Http::fake([
            'http://backend-svc/*' => Http::response('ok', 200),
        ]);

        $this->get('/proxy/users/1');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Request-Id');
        });
    }

    #[WithConfig('lararoxy.logging.default_level', 'minimal')]
    public function test_proxy_logs_request_to_database(): void
    {
        Http::fake([
            'http://backend-svc/*' => Http::response(['ok' => true], 200),
        ]);

        $this->get('/proxy/users/1');

        $this->assertDatabaseHas('proxy_logs', [
            'group' => 'api',
            'method' => 'GET',
        ]);
    }

    public function test_proxy_handles_multiple_sequential_requests(): void
    {
        Http::fake([
            'http://backend-svc/*' => Http::response(['ok' => true], 200),
        ]);

        $this->get('/proxy/users/1')->assertOk();
        $this->get('/proxy/users/2')->assertOk();
        $this->postJson('/proxy/orders', ['item' => 'a'])->assertOk();
    }
}
