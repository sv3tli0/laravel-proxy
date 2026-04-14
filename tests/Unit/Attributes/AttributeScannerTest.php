<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Attributes;

use Lararoxy\Support\AttributeScanner;
use Lararoxy\Tests\TestCase;

class AttributeScannerTest extends TestCase
{
    public function test_scan_empty_paths_discovers_nothing(): void
    {
        $scanner = new AttributeScanner;
        $scanner->scan([]);

        $this->assertEmpty($scanner->getServices());
        $this->assertEmpty($scanner->getGroups());
        $this->assertEmpty($scanner->getOutgoing());
    }

    public function test_scan_nonexistent_path_discovers_nothing(): void
    {
        $scanner = new AttributeScanner;
        $scanner->scan(['/nonexistent/path']);

        $this->assertEmpty($scanner->getServices());
    }

    public function test_ignores_files_without_class_definitions(): void
    {
        $dir = $this->createFixture('helper_funcs', "<?php\nfunction helper() { return true; }\n");

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $this->assertEmpty($scanner->getServices());
    }

    public function test_ignores_classes_without_lararoxy_attributes(): void
    {
        $dir = $this->createFixture('PlainClass', "<?php\nnamespace Lararoxy\\Tests\\Temp;\nclass PlainClass {}\n");

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $this->assertEmpty($scanner->getServices());
    }

    public function test_discovers_upstream_service_with_retry_and_circuit_breaker(): void
    {
        $dir = $this->createFixture('SvcFixture', <<<'PHP'
<?php
namespace Lararoxy\Tests\Fixtures;
use Lararoxy\Attributes\UpstreamService;
use Lararoxy\Attributes\Retry;
use Lararoxy\Attributes\CircuitBreaker;

#[UpstreamService(name: 'fixture-svc', baseUrl: 'http://fixture', timeout: 10)]
#[Retry(times: 2, delay: 50)]
#[CircuitBreaker(threshold: 3, timeout: 15)]
class SvcFixture {}
PHP);

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $svc = $scanner->getServices()['fixture-svc'];
        $this->assertSame('http://fixture', $svc->baseUrl);
        $this->assertSame(10, $svc->timeout);
        $this->assertSame(2, $svc->retry->times);
        $this->assertTrue($svc->circuitBreaker->enabled);
        $this->assertSame(3, $svc->circuitBreaker->threshold);
    }

    public function test_discovers_endpoint_group_with_auth_logging_and_routes(): void
    {
        $dir = $this->createFixture('GrpFixture', <<<'PHP'
<?php
namespace Lararoxy\Tests\Fixtures;
use Lararoxy\Attributes\EndpointGroup;
use Lararoxy\Attributes\Auth;
use Lararoxy\Attributes\ProxyRoute;
use Lararoxy\Attributes\Logging;

#[EndpointGroup(name: 'test-group', prefix: '/api')]
#[Auth(driver: 'sanctum', guard: 'api')]
#[Logging(level: 'standard', samplingRate: 0.5)]
class GrpFixture
{
    #[ProxyRoute(method: 'GET', path: '/users/{id}', upstream: 'users', upstreamPath: '/api/users/{id}')]
    public function getUser() {}

    #[ProxyRoute(method: 'POST', path: '/orders', upstream: 'orders', upstreamPath: '/api/orders')]
    public function createOrder() {}
}
PHP);

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $group = $scanner->getGroups()['test-group'];
        $this->assertSame('/api', $group->prefix);
        $this->assertSame('sanctum', $group->auth['driver']);
        $this->assertSame('standard', $group->logging['level']);
        $this->assertSame(0.5, $group->logging['sampling']['rate']);
        $this->assertCount(2, $group->routes);
        $this->assertSame('GET', $group->routes[0]->method);
        $this->assertSame('/api/users/{id}', $group->routes[0]->upstreamPath);
    }

    public function test_discovers_group_with_rate_limit_and_token_payload(): void
    {
        $dir = $this->createFixture('RlGrpFixture', <<<'PHP'
<?php
namespace Lararoxy\Tests\Fixtures;
use Lararoxy\Attributes\EndpointGroup;
use Lararoxy\Attributes\RateLimit;
use Lararoxy\Attributes\ProxyRoute;

#[EndpointGroup(name: 'rl-group', prefix: '/rl', tokenPayload: 'App\\MyPayload')]
#[RateLimit(maxAttempts: 100, decayMinutes: 5)]
class RlGrpFixture
{
    #[ProxyRoute(method: 'GET', path: '/data', upstream: 'svc', upstreamPath: '/data')]
    public function getData() {}
}
PHP);

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $group = $scanner->getGroups()['rl-group'];
        $this->assertSame('100,5', $group->rateLimit);
        $this->assertSame('App\\MyPayload', $group->tokenPayload);
    }

    public function test_discovers_aggregate_routes(): void
    {
        $dir = $this->createFixture('AggGrpFixture', <<<'PHP'
<?php
namespace Lararoxy\Tests\Fixtures;
use Lararoxy\Attributes\EndpointGroup;
use Lararoxy\Attributes\AggregateRoute;
use Lararoxy\Attributes\AggregateSource;

#[EndpointGroup(name: 'agg-group', prefix: '/bff')]
class AggGrpFixture
{
    #[AggregateRoute(method: 'GET', path: '/home', responseTransformer: 'App\\HomeTransformer')]
    #[AggregateSource(upstream: 'users', path: '/me', as: 'profile')]
    #[AggregateSource(upstream: 'feed', path: '/posts', as: 'feed')]
    public function home() {}
}
PHP);

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $route = $scanner->getGroups()['agg-group']->routes[0];
        $this->assertNotNull($route->aggregate);
        $this->assertCount(2, $route->aggregate);
        $this->assertSame('profile', $route->aggregate[0]['as']);
        $this->assertSame('App\\HomeTransformer', $route->responseTransformer);
    }

    public function test_discovers_method_level_logging(): void
    {
        $dir = $this->createFixture('MethodLogFixture', <<<'PHP'
<?php
namespace Lararoxy\Tests\Fixtures;
use Lararoxy\Attributes\EndpointGroup;
use Lararoxy\Attributes\ProxyRoute;
use Lararoxy\Attributes\Logging;

#[EndpointGroup(name: 'mlog-group', prefix: '/ml')]
class MethodLogFixture
{
    #[ProxyRoute(method: 'GET', path: '/data', upstream: 'svc', upstreamPath: '/data')]
    #[Logging(level: 'full', samplingRate: 0.25)]
    public function getData() {}
}
PHP);

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $route = $scanner->getGroups()['mlog-group']->routes[0];
        $this->assertSame('full', $route->logging['level']);
        $this->assertSame(0.25, $route->logging['sampling']['rate']);
    }

    public function test_discovers_outgoing_service_full(): void
    {
        $dir = $this->createFixture('OutFixture', <<<'PHP'
<?php
namespace Lararoxy\Tests\Fixtures;
use Lararoxy\Attributes\OutgoingService;
use Lararoxy\Attributes\Retry;
use Lararoxy\Attributes\Tracking;
use Lararoxy\Attributes\Callback;

#[OutgoingService(name: 'pay-gw', baseUrl: 'http://pay', timeout: 60)]
#[Retry(times: 3)]
#[Tracking(store: 'database', ttl: 3600)]
#[Callback(path: '/webhooks/pay/{tracking_id}', handler: 'App\\Handlers\\Pay')]
class OutFixture {}
PHP);

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $svc = $scanner->getOutgoing()['pay-gw'];
        $this->assertSame(60, $svc->timeout);
        $this->assertSame(3, $svc->retry->times);
        $this->assertSame(3600, $svc->tracking->ttl);
        $this->assertSame('/webhooks/pay/{tracking_id}', $svc->callback->path);
    }

    public function test_discovers_outgoing_service_minimal(): void
    {
        $dir = $this->createFixture('MinOutFixture', <<<'PHP'
<?php
namespace Lararoxy\Tests\Fixtures;
use Lararoxy\Attributes\OutgoingService;

#[OutgoingService(name: 'minimal-out', baseUrl: 'http://out')]
class MinOutFixture {}
PHP);

        $scanner = new AttributeScanner;
        $scanner->scan([$dir]);

        $svc = $scanner->getOutgoing()['minimal-out'];
        $this->assertNull($svc->retry);
        $this->assertNull($svc->tracking);
        $this->assertNull($svc->callback);
    }

    protected function createFixture(string $name, string $code): string
    {
        $dir = sys_get_temp_dir().'/lararoxy-test-'.uniqid();
        @mkdir($dir, 0755, true);
        file_put_contents($dir.'/'.$name.'.php', $code);
        require_once $dir.'/'.$name.'.php';

        return $dir;
    }
}
