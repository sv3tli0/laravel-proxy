<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Builders;

use Lararoxy\Builders\AggregateBuilder;
use Lararoxy\Builders\GroupBuilder;
use Lararoxy\Builders\OutgoingBuilder;
use Lararoxy\Builders\RouteBuilder;
use Lararoxy\Builders\RouteCollector;
use Lararoxy\Builders\ServiceBuilder;
use Lararoxy\Builders\WebhookBuilder;
use Lararoxy\Builders\WebhookGroupBuilder;
use Lararoxy\Tests\TestCase;

class BuilderTest extends TestCase
{
    // ── ServiceBuilder ──

    public function test_service_builder_full_config(): void
    {
        $svc = (new ServiceBuilder('users'))
            ->baseUrl('http://users-svc')
            ->timeout(15)
            ->connectTimeout(3)
            ->retry(times: 2, delay: 200)
            ->circuitBreaker(threshold: 3, timeout: 60)
            ->healthCheck('/status', 60)
            ->bearerAuth('secret-token')
            ->build();

        $this->assertSame('users', $svc->name);
        $this->assertSame('http://users-svc', $svc->baseUrl);
        $this->assertSame(15, $svc->timeout);
        $this->assertSame(3, $svc->connectTimeout);
        $this->assertSame(2, $svc->retry->times);
        $this->assertSame(3, $svc->circuitBreaker->threshold);
        $this->assertSame('/status', $svc->healthCheck->path);
        $this->assertSame('bearer', $svc->auth['type']);
    }

    public function test_service_builder_hmac_auth(): void
    {
        $svc = (new ServiceBuilder('svc'))->baseUrl('http://svc')->hmacAuth('key', 'sha512')->build();

        $this->assertSame('hmac', $svc->auth['type']);
        $this->assertSame('sha512', $svc->auth['algorithm']);
    }

    public function test_service_builder_api_key_auth(): void
    {
        $svc = (new ServiceBuilder('svc'))->baseUrl('http://svc')->apiKeyAuth('k', 'X-Key')->build();

        $this->assertSame('api-key', $svc->auth['type']);
        $this->assertSame('k', $svc->auth['key']);
    }

    // ── GroupBuilder ──

    public function test_group_builder_with_routes_callback(): void
    {
        $group = (new GroupBuilder('api'))
            ->prefix('/api/v1')
            ->auth('sanctum', 'api')
            ->tokenPayload('App\\Payloads\\User')
            ->middleware('throttle:60,1')
            ->logging('standard', 0.5)
            ->routes(function (RouteCollector $r) {
                $r->get('/users/{id}', 'users', '/api/users/{id}');
                $r->post('/orders', 'orders', '/api/orders');
            })
            ->build();

        $this->assertSame('/api/v1', $group->prefix);
        $this->assertSame('sanctum', $group->auth['driver']);
        $this->assertSame('App\\Payloads\\User', $group->tokenPayload);
        $this->assertCount(2, $group->routes);
        $this->assertSame('GET', $group->routes[0]->method);
    }

    public function test_group_builder_domain_cors_pipeline_ratelimit(): void
    {
        $group = (new GroupBuilder('api'))
            ->domain('api.example.com')
            ->cors(['allowed_origins' => ['*']])
            ->pipeline('App\\Stage1', 'App\\Stage2')
            ->rateLimit(100, 5)
            ->build();

        $this->assertSame('api.example.com', $group->domain);
        $this->assertSame(['allowed_origins' => ['*']], $group->cors);
        $this->assertSame(['App\\Stage1', 'App\\Stage2'], $group->pipeline);
        $this->assertSame('100,5', $group->rateLimit);
    }

    public function test_group_builder_add_route_directly(): void
    {
        $group = (new GroupBuilder('api'))
            ->addRoute(new RouteBuilder('GET', '/x', 'svc', '/x'))
            ->build();

        $this->assertCount(1, $group->routes);
        $this->assertSame('/x', $group->routes[0]->path);
    }

    // ── RouteBuilder ──

    public function test_route_builder_all_chainable_methods(): void
    {
        $route = (new RouteBuilder('GET', '/test', 'svc', '/test'))
            ->upstreamMethod('PATCH')
            ->transformResponse('App\\Transformer')
            ->cache(300, 'custom-key')
            ->logging('full')
            ->wildcard()
            ->injectBody(['extra' => true])
            ->build();

        $this->assertSame('PATCH', $route->upstreamMethod);
        $this->assertSame('App\\Transformer', $route->responseTransformer);
        $this->assertSame(['ttl' => 300, 'key' => 'custom-key'], $route->cache);
        $this->assertSame(['level' => 'full'], $route->logging);
        $this->assertTrue($route->wildcard);
        $this->assertSame(['extra' => true], $route->injectBody);
    }

    // ── RouteCollector ──

    public function test_route_collector_all_http_methods(): void
    {
        $collector = new RouteCollector;
        $collector->get('/a', 'svc', '/a');
        $collector->post('/b', 'svc', '/b');
        $collector->put('/c', 'svc', '/c');
        $collector->patch('/d', 'svc', '/d');
        $collector->delete('/e', 'svc', '/e');

        $routes = array_map(fn ($r) => $r->build(), $collector->getRoutes());

        $this->assertCount(5, $routes);
        $this->assertSame('GET', $routes[0]->method);
        $this->assertSame('DELETE', $routes[4]->method);
    }

    public function test_route_collector_aggregate(): void
    {
        $collector = new RouteCollector;
        $collector->get('/users', 'users', '/users');
        $collector->aggregate('/home')
            ->from('users', '/me', as: 'user')
            ->from('posts', '/feed', as: 'feed');

        $routes = array_map(fn ($r) => $r->build(), $collector->getRoutes());

        $this->assertNull($routes[0]->aggregate);
        $this->assertCount(2, $routes[1]->aggregate);
    }

    // ── OutgoingBuilder ──

    public function test_outgoing_builder_full_config(): void
    {
        $out = (new OutgoingBuilder('payments'))
            ->baseUrl('http://pay')
            ->timeout(60)
            ->hmacAuth('key', 'sha256')
            ->retry(times: 3, delay: 1000)
            ->tracking(store: 'database', ttl: 86400)
            ->callback('/hooks/{id}', 'App\\Handler', signatureHeader: 'X-Sig')
            ->queued('redis', 'high')
            ->logging('full')
            ->build();

        $this->assertSame('payments', $out->name);
        $this->assertSame(60, $out->timeout);
        $this->assertSame('hmac', $out->auth['type']);
        $this->assertSame(3, $out->retry->times);
        $this->assertSame('database', $out->tracking->store);
        $this->assertSame('/hooks/{id}', $out->callback->path);
        $this->assertSame(['enabled' => true, 'connection' => 'redis', 'queue' => 'high'], $out->queue);
        $this->assertSame(['level' => 'full'], $out->logging);
    }

    public function test_outgoing_builder_bearer_and_api_key_auth(): void
    {
        $bearer = (new OutgoingBuilder('a'))->baseUrl('http://a')->bearerAuth('tok')->build();
        $this->assertSame('bearer', $bearer->auth['type']);

        $apiKey = (new OutgoingBuilder('b'))->baseUrl('http://b')->apiKeyAuth('k', 'X-K')->build();
        $this->assertSame('api-key', $apiKey->auth['type']);
    }

    // ── AggregateBuilder ──

    public function test_aggregate_builder(): void
    {
        $route = (new AggregateBuilder('/dashboard'))
            ->method('POST')
            ->from('users', '/me', as: 'profile')
            ->from('feed', '/posts', as: 'feed')
            ->from('notifs', '/unread', as: 'notifications')
            ->transformResponse('App\\DashboardTransformer')
            ->build();

        $this->assertSame('POST', $route->method);
        $this->assertSame('/dashboard', $route->path);
        $this->assertCount(3, $route->aggregate);
        $this->assertSame('App\\DashboardTransformer', $route->responseTransformer);
    }

    // ── WebhookBuilder ──

    public function test_webhook_builder(): void
    {
        $builder = (new WebhookBuilder('/pay/{id}'))
            ->matchTracking()
            ->verifySignature('X-Sig', 'App\\Verifier')
            ->handle('App\\Handler');

        $data = $builder->toArray();
        $this->assertTrue($data['match_tracking']);
        $this->assertSame('X-Sig', $data['signature_header']);
        $this->assertSame('App\\Handler', $data['handler']);
    }

    public function test_webhook_group_builder(): void
    {
        $group = new WebhookGroupBuilder(function ($wh) {
            $wh->webhook('/a')->handle('App\\A');
            $wh->webhook('/b')->handle('App\\B');
        });
        $group->prefix('/hooks');

        $this->assertSame('/hooks', $group->getPrefix());
        $this->assertCount(2, $group->getWebhooks());
    }
}
