<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Lararoxy\Builders\AggregateBuilder;
use Lararoxy\Contracts\LogDriverContract;
use Lararoxy\Contracts\TrackingIdGenerator;
use Lararoxy\Contracts\TrackingStoreContract;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Data\TrackingConfig;
use Lararoxy\Facades\Lararoxy;
use Lararoxy\Facades\Proxy;
use Lararoxy\Http\CircuitBreakerManager;
use Lararoxy\Http\ProxyHttpClient;
use Lararoxy\LararoxyManager;
use Lararoxy\Logging\RequestLogger;
use Lararoxy\Outgoing\OutgoingDispatcher;
use Lararoxy\Support\AttributeScanner;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_manager_is_singleton(): void
    {
        $this->assertSame(
            $this->app->make(LararoxyManager::class),
            $this->app->make(LararoxyManager::class),
        );
    }

    public function test_manager_bound_as_alias(): void
    {
        $this->assertInstanceOf(LararoxyManager::class, $this->app->make('lararoxy'));
    }

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('lararoxy'));
        $this->assertIsArray(config('lararoxy.services'));
    }

    public function test_all_core_bindings_resolve(): void
    {
        $this->assertInstanceOf(ConfigRegistry::class, $this->app->make(ConfigRegistry::class));
        $this->assertInstanceOf(TrackingIdGenerator::class, $this->app->make(TrackingIdGenerator::class));
        $this->assertInstanceOf(TrackingStoreContract::class, $this->app->make(TrackingStoreContract::class));
        $this->assertInstanceOf(LogDriverContract::class, $this->app->make(LogDriverContract::class));
        $this->assertInstanceOf(RequestLogger::class, $this->app->make(RequestLogger::class));
        $this->assertInstanceOf(CircuitBreakerManager::class, $this->app->make(CircuitBreakerManager::class));
        $this->assertInstanceOf(ProxyHttpClient::class, $this->app->make(ProxyHttpClient::class));
        $this->assertInstanceOf(OutgoingDispatcher::class, $this->app->make(OutgoingDispatcher::class));
    }

    public function test_facades_resolve_to_manager(): void
    {
        $this->assertInstanceOf(LararoxyManager::class, Lararoxy::getFacadeRoot());
        $this->assertInstanceOf(LararoxyManager::class, Proxy::getFacadeRoot());
    }

    public function test_manager_group_registers_on_finalize(): void
    {
        $manager = $this->app->make(LararoxyManager::class);

        $manager->group('test-grp', function ($routes) {
            $routes->get('/health', 'svc', '/health');
        })->prefix('/test');

        $manager->finalize();

        $this->assertTrue($manager->registry()->hasGroup('test-grp'));
        $this->assertSame('/test', $manager->registry()->getGroup('test-grp')->prefix);
        $this->assertCount(1, $manager->registry()->getGroup('test-grp')->routes);
    }

    public function test_manager_aggregate_and_webhooks(): void
    {
        $manager = $this->app->make(LararoxyManager::class);

        $this->assertInstanceOf(AggregateBuilder::class, $manager->aggregate('/dash'));

        $wh = $manager->webhooks(fn ($w) => $w->webhook('/pay/{id}')->handle('App\\H'));
        $this->assertCount(1, $wh->getWebhooks());
    }

    public function test_manager_fake_enables_assertions(): void
    {
        $manager = $this->app->make(LararoxyManager::class);
        $this->assertFalse($manager->isFaked());

        $fake = $manager->fake();
        $this->assertTrue($manager->isFaked());

        $fake->recordSent('users', 'GET', '/users/1');
        $manager->assertSent('users');
        $manager->assertNotSent('orders');
        $manager->assertSentCount('users', 1);
    }

    public function test_manager_fake_maps_service_urls(): void
    {
        $registry = $this->app->make(ConfigRegistry::class);
        $registry->registerService(new ServiceDefinition(name: 'users', baseUrl: 'http://users'));
        $registry->registerOutgoing(new OutgoingServiceDefinition(
            name: 'pay', baseUrl: 'http://pay',
            tracking: new TrackingConfig,
        ));

        $manager = $this->app->make(LararoxyManager::class);
        $manager->fake([
            'users' => LararoxyManager::response(['id' => 1], 200),
            'pay' => LararoxyManager::response(['ok' => true], 201),
        ]);

        $this->assertTrue($manager->isFaked());
    }

    public function test_config_registry_boots_with_attribute_scanning(): void
    {
        $dir = sys_get_temp_dir().'/lararoxy-boot-'.uniqid();
        @mkdir($dir, 0755, true);
        file_put_contents($dir.'/BootSvc.php', <<<'PHP'
<?php
namespace Lararoxy\Tests\BootFixtures;
use Lararoxy\Attributes\UpstreamService;
#[UpstreamService(name: 'boot-svc', baseUrl: 'http://boot')]
class BootSvc {}
PHP);
        require_once $dir.'/BootSvc.php';

        $registry = new ConfigRegistry(new AttributeScanner);
        $registry->boot(['attributes' => ['enabled' => true, 'scan_paths' => [$dir]]]);

        $this->assertTrue($registry->hasService('boot-svc'));
    }
}
