<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Lararoxy\Data\CircuitBreakerConfig;
use Lararoxy\Data\RetryConfig;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Enums\CircuitState;
use Lararoxy\Exceptions\UpstreamException;
use Lararoxy\Http\BearerAuthenticator;
use Lararoxy\Http\CircuitBreakerManager;
use Lararoxy\Http\ProxyHttpClient;
use Lararoxy\Tests\TestCase;

class ProxyHttpClientTest extends TestCase
{
    public function test_sends_get_request(): void
    {
        Http::fake(['http://svc/*' => Http::response(['id' => 1], 200)]);

        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc');

        $response = $client->send($service, 'GET', '/users/1');

        $this->assertSame(200, $response->status());
        $this->assertSame(1, $response->json('id'));
    }

    public function test_sends_post_with_json_body(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 201)]);

        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc');

        $response = $client->send($service, 'POST', '/orders', body: ['item' => 'widget']);

        $this->assertSame(201, $response->status());
        Http::assertSent(fn ($req) => $req->method() === 'POST');
    }

    public function test_sends_post_with_string_body(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc');

        $client->send($service, 'POST', '/raw', body: 'raw-data');
        Http::assertSentCount(1);
    }

    public function test_applies_authenticator(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc');

        $client->send($service, 'GET', '/test', authenticator: new BearerAuthenticator('tok'));

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_applies_retry_config(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc', retry: new RetryConfig(times: 2, delay: 10));

        $response = $client->send($service, 'GET', '/test');
        $this->assertSame(200, $response->status());
    }

    public function test_forwards_custom_headers(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc');

        $client->send($service, 'GET', '/test', headers: ['X-Custom' => 'value']);

        Http::assertSent(fn ($req) => $req->hasHeader('X-Custom', 'value'));
    }

    public function test_records_circuit_breaker_failure_on_5xx(): void
    {
        Http::fake(['*' => Http::response('error', 503)]);

        $cbConfig = new CircuitBreakerConfig(enabled: true, threshold: 5, timeout: 30);
        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc', circuitBreaker: $cbConfig);

        $response = $client->send($service, 'GET', '/test');
        $this->assertSame(503, $response->status());
    }

    public function test_wraps_connection_exception_as_upstream_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $client = new ProxyHttpClient(new CircuitBreakerManager);
        $service = new ServiceDefinition(name: 'svc', baseUrl: 'http://svc');

        $this->expectException(UpstreamException::class);
        $this->expectExceptionMessage('svc');

        $client->send($service, 'GET', '/test');
    }

    public function test_connection_exception_records_circuit_breaker_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $cb = new CircuitBreakerManager;
        $cbConfig = new CircuitBreakerConfig(enabled: true, threshold: 1, timeout: 30);
        $client = new ProxyHttpClient($cb);
        $service = new ServiceDefinition(name: 'fail-svc', baseUrl: 'http://svc', circuitBreaker: $cbConfig);

        try {
            $client->send($service, 'GET', '/test');
        } catch (UpstreamException) {
            // expected
        }

        $this->assertSame(CircuitState::Open, $cb->getState('fail-svc'));
    }
}
