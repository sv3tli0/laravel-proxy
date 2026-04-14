<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Pipeline;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Lararoxy\Auth\AuthDriverFactory;
use Lararoxy\Contracts\AuthModel;
use Lararoxy\Contracts\ResponseTransformer;
use Lararoxy\Contracts\TokenPayload;
use Lararoxy\Data\CircuitBreakerConfig;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\ProxyContext;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Http\CircuitBreakerManager;
use Lararoxy\Logging\DatabaseLogDriver;
use Lararoxy\Logging\FieldRedactor;
use Lararoxy\Logging\LogSampler;
use Lararoxy\Logging\RequestLogger;
use Lararoxy\Pipeline\Stages\AuthenticationStage;
use Lararoxy\Pipeline\Stages\AuthorizationStage;
use Lararoxy\Pipeline\Stages\PostLoggingStage;
use Lararoxy\Pipeline\Stages\PreLoggingStage;
use Lararoxy\Pipeline\Stages\RateLimitingStage;
use Lararoxy\Pipeline\Stages\RequestTransformStage;
use Lararoxy\Pipeline\Stages\ResponseTransformStage;
use Lararoxy\Pipeline\Stages\TokenPayloadBuildStage;
use Lararoxy\Pipeline\Stages\UpstreamDispatchStage;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;
use Laravel\Sanctum\Contracts\HasAbilities;

class PipelineStageTest extends TestCase
{
    private function makeContext(array $groupArgs = [], array $routeArgs = []): ProxyContext
    {
        $g = array_merge(['name' => 'test'], $groupArgs);
        $r = array_merge(['method' => 'GET', 'path' => '/test'], $routeArgs);

        return new ProxyContext(
            request: Request::create('/test'),
            group: new GroupDefinition(...$g),
            route: new RouteDefinition(...$r),
        );
    }

    // ── RateLimiting ──

    public function test_rate_limiting_passes_without_config(): void
    {
        $stage = new RateLimitingStage(app(RateLimiter::class));
        $called = false;

        $stage->handle($this->makeContext(), function () use (&$called) {
            $called = true;

            return new Response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_rate_limiting_returns_429_when_exceeded(): void
    {
        $stage = new RateLimitingStage(app(RateLimiter::class));

        // First request passes
        $r1 = $stage->handle($this->makeContext(['rateLimit' => '1,1']), fn () => new Response('ok'));
        $this->assertSame(200, $r1->getStatusCode());

        // Second request is rate limited
        $r2 = $stage->handle($this->makeContext(['rateLimit' => '1,1']), fn () => new Response('ok'));
        $this->assertSame(429, $r2->getStatusCode());
        $this->assertNotEmpty($r2->headers->get('Retry-After'));
    }

    // ── Authentication ──

    public function test_authentication_passes_without_auth_config(): void
    {
        $stage = new AuthenticationStage(new AuthDriverFactory);
        $called = false;

        $stage->handle($this->makeContext(), function () use (&$called) {
            $called = true;

            return new Response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_authentication_returns_401_when_driver_rejects(): void
    {
        $stage = new AuthenticationStage(new AuthDriverFactory);
        $ctx = $this->makeContext(['auth' => ['driver' => 'api-key', 'header' => 'X-Key']]);

        $result = $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame(401, $result->getStatusCode());
    }

    public function test_passthrough_auth_passes_with_null_model(): void
    {
        $stage = new AuthenticationStage(new AuthDriverFactory);
        $called = false;

        $stage->handle(
            $this->makeContext(['auth' => ['driver' => 'passthrough']]),
            function ($ctx) use (&$called) {
                $called = true;
                $this->assertNull($ctx->authModel);

                return new Response('ok');
            },
        );

        $this->assertTrue($called);
    }

    // ── TokenPayload Build ──

    public function test_token_payload_skips_without_auth_model(): void
    {
        $stage = new TokenPayloadBuildStage;
        $ctx = $this->makeContext();

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertNull($ctx->tokenPayload);
    }

    public function test_token_payload_builds_from_auth_model(): void
    {
        $stage = new TokenPayloadBuildStage;
        $ctx = $this->makeContext();
        $ctx->authModel = new StubAuthModel;

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertInstanceOf(StubTokenPayload::class, $ctx->tokenPayload);
        $this->assertSame('42', $ctx->upstreamHeaders['X-User-Id']);
    }

    public function test_token_payload_uses_group_override_class(): void
    {
        $stage = new TokenPayloadBuildStage;
        $ctx = $this->makeContext(['tokenPayload' => StubTokenPayload::class]);
        $ctx->authModel = new StubAuthModel;

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertInstanceOf(StubTokenPayload::class, $ctx->tokenPayload);
    }

    public function test_token_payload_skips_non_auth_model(): void
    {
        $stage = new TokenPayloadBuildStage;
        $ctx = $this->makeContext();
        $ctx->authModel = new \stdClass;

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertNull($ctx->tokenPayload);
    }

    // ── Authorization ──

    public function test_authorization_passes_without_payload(): void
    {
        $stage = new AuthorizationStage;
        $called = false;

        $stage->handle($this->makeContext(), function () use (&$called) {
            $called = true;

            return new Response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_authorization_returns_403_when_rejected(): void
    {
        $stage = new AuthorizationStage;
        $ctx = $this->makeContext();
        $ctx->tokenPayload = new StubTokenPayload(authorized: false);

        $result = $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame(403, $result->getStatusCode());
    }

    // ── RequestTransform ──

    public function test_request_transform_resolves_path_variables(): void
    {
        $stage = new RequestTransformStage;
        $ctx = new ProxyContext(
            request: Request::create('/test'),
            group: new GroupDefinition(name: 'api'),
            route: new RouteDefinition(method: 'GET', path: '/users/{id}', upstream: 'svc', upstreamPath: '/api/users/{id}'),
        );

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame('/api/users/{id}', $ctx->resolvedUpstreamPath);
    }

    public function test_request_transform_resolves_token_variables(): void
    {
        $stage = new RequestTransformStage;
        $ctx = new ProxyContext(
            request: Request::create('/me/orders'),
            group: new GroupDefinition(name: 'api'),
            route: new RouteDefinition(method: 'GET', path: '/me/orders', upstream: 'svc', upstreamPath: '/api/users/{token.user_id}/orders'),
        );
        $ctx->tokenPayload = new StubTokenPayload;

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame('/api/users/42/orders', $ctx->resolvedUpstreamPath);
    }

    public function test_request_transform_applies_method_override(): void
    {
        $stage = new RequestTransformStage;
        $ctx = new ProxyContext(
            request: Request::create('/test', 'POST'),
            group: new GroupDefinition(name: 'api'),
            route: new RouteDefinition(method: 'POST', path: '/act', upstream: 'svc', upstreamPath: '/act', upstreamMethod: 'PATCH'),
        );

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame('PATCH', $ctx->resolvedUpstreamMethod);
    }

    public function test_request_transform_injects_body_fields(): void
    {
        $stage = new RequestTransformStage;
        $ctx = new ProxyContext(
            request: Request::create('/test', 'POST', ['name' => 'orig']),
            group: new GroupDefinition(name: 'api'),
            route: new RouteDefinition(method: 'POST', path: '/test', upstream: 'svc', upstreamPath: '/test', injectBody: ['source' => 'proxy']),
        );

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame('proxy', $ctx->request->input('source'));
    }

    // ── PreLogging ──

    public function test_pre_logging_assigns_request_id(): void
    {
        $ctx = $this->makeContext();
        (new PreLoggingStage)->handle($ctx, fn () => new Response('ok'));

        $this->assertNotNull($ctx->requestId);
    }

    public function test_pre_logging_trusts_incoming_id_when_configured(): void
    {
        $this->app['config']->set('lararoxy.request_id.trust_incoming', true);

        $request = Request::create('/test');
        $request->headers->set('X-Request-Id', 'incoming-123');

        $ctx = new ProxyContext(
            request: $request,
            group: new GroupDefinition(name: 'api'),
            route: new RouteDefinition(method: 'GET', path: '/test'),
        );

        (new PreLoggingStage)->handle($ctx, fn () => new Response('ok'));

        $this->assertSame('incoming-123', $ctx->requestId);
    }

    // ── UpstreamDispatch ──

    public function test_upstream_dispatch_returns_502_without_upstream(): void
    {
        $stage = $this->app->make(UpstreamDispatchStage::class);
        $ctx = $this->makeContext();

        $result = $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame(502, $result->getStatusCode());
    }

    public function test_upstream_dispatch_forwards_to_service(): void
    {
        Http::fake(['http://svc/*' => Http::response(['ok' => true], 200)]);

        $this->app->make(ConfigRegistry::class)
            ->registerService(new ServiceDefinition(name: 'svc', baseUrl: 'http://svc'));

        $stage = $this->app->make(UpstreamDispatchStage::class);
        $ctx = $this->makeContext(routeArgs: ['upstream' => 'svc', 'upstreamPath' => '/data']);
        $ctx->resolvedUpstreamPath = '/data';
        $ctx->resolvedUpstreamMethod = 'GET';

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertNotNull($ctx->upstreamResponse);
        $this->assertSame(200, $ctx->upstreamResponse->status());
    }

    public function test_upstream_dispatch_returns_503_on_circuit_open(): void
    {
        $cbConfig = new CircuitBreakerConfig(enabled: true, threshold: 1, timeout: 60);
        $this->app->make(ConfigRegistry::class)
            ->registerService(new ServiceDefinition(name: 'svc', baseUrl: 'http://svc', circuitBreaker: $cbConfig));
        $this->app->make(CircuitBreakerManager::class)
            ->recordFailure('svc', $cbConfig);

        $stage = $this->app->make(UpstreamDispatchStage::class);
        $ctx = $this->makeContext(routeArgs: ['upstream' => 'svc', 'upstreamPath' => '/test']);
        $ctx->resolvedUpstreamPath = '/test';

        $result = $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame(503, $result->getStatusCode());
        $this->assertNotEmpty($result->headers->get('Retry-After'));
    }

    public function test_upstream_dispatch_returns_502_on_connection_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->app->make(ConfigRegistry::class)
            ->registerService(new ServiceDefinition(name: 'svc', baseUrl: 'http://svc'));

        $stage = $this->app->make(UpstreamDispatchStage::class);
        $ctx = $this->makeContext(routeArgs: ['upstream' => 'svc', 'upstreamPath' => '/test']);
        $ctx->resolvedUpstreamPath = '/test';
        $ctx->resolvedUpstreamMethod = 'GET';

        $result = $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame(502, $result->getStatusCode());
    }

    // ── ResponseTransform ──

    public function test_response_transform_applies_transformer(): void
    {
        $this->app->bind(StubResponseTransformer::class, fn () => new StubResponseTransformer);

        $stage = new ResponseTransformStage;
        $ctx = $this->makeContext(routeArgs: ['responseTransformer' => StubResponseTransformer::class]);
        $ctx->upstreamResponse = 'original';

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame('transformed', $ctx->upstreamResponse);
    }

    public function test_response_transform_passes_through_without_transformer(): void
    {
        $stage = new ResponseTransformStage;
        $ctx = $this->makeContext();
        $ctx->upstreamResponse = 'untouched';

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertSame('untouched', $ctx->upstreamResponse);
    }

    // ── PostLogging ──

    public function test_post_logging_stage_logs_request(): void
    {
        $logger = new RequestLogger(
            driver: new DatabaseLogDriver,
            redactor: new FieldRedactor,
            sampler: new LogSampler(enabled: false),
            bodySizeLimit: 16384, excludePaths: [], escalation: [],
        );

        $stage = new PostLoggingStage($logger);
        $ctx = $this->makeContext();
        $ctx->requestId = 'req_post';

        $stage->handle($ctx, fn () => new Response('ok'));

        $this->assertDatabaseHas('proxy_logs', ['request_id' => 'req_post']);
    }
}

// ── Stubs ──

class StubAuthModel implements AuthModel
{
    public int $id = 42;

    public string $email = 'test@test.com';

    public function tokenPayloadClass(): string
    {
        return StubTokenPayload::class;
    }

    public function currentAccessToken(): ?HasAbilities
    {
        return null;
    }
}

class StubTokenPayload implements TokenPayload
{
    public function __construct(
        public int $userId = 42,
        public string $email = 'test@test.com',
        public bool $authorized = true,
    ) {}

    public static function fromAuth(AuthModel $model, ?HasAbilities $accessToken = null): static
    {
        return new static(userId: $model->id, email: $model->email);
    }

    public function authorize(string $routeName, array $routeParams): bool
    {
        return $this->authorized;
    }

    public function upstreamHeaders(): array
    {
        return ['X-User-Id' => (string) $this->userId];
    }

    public function resolve(string $key): mixed
    {
        return match ($key) {
            'user_id' => $this->userId,
            'email' => $this->email,
            default => null,
        };
    }
}

class StubResponseTransformer implements ResponseTransformer
{
    public function transform(mixed $response, Request $originalRequest): mixed
    {
        return 'transformed';
    }
}
