<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Exceptions\ServiceNotFoundException;
use Lararoxy\Support\AttributeScanner;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;

class ConfigRegistryTest extends TestCase
{
    private ConfigRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ConfigRegistry(new AttributeScanner);
        $this->registry->boot(['attributes' => ['enabled' => false]]);
    }

    public function test_loads_services_from_config(): void
    {
        $registry = new ConfigRegistry(new AttributeScanner);
        $registry->boot([
            'services' => ['users' => ['base_url' => 'http://users', 'timeout' => 10]],
            'attributes' => ['enabled' => false],
        ]);

        $this->assertSame('http://users', $registry->getService('users')->baseUrl);
        $this->assertSame(10, $registry->getService('users')->timeout);
    }

    public function test_loads_groups_from_config(): void
    {
        $registry = new ConfigRegistry(new AttributeScanner);
        $registry->boot([
            'groups' => [
                'api' => [
                    'prefix' => '/api/v1',
                    'routes' => [['method' => 'GET', 'path' => '/users', 'upstream' => 'u', 'upstream_path' => '/u']],
                ],
            ],
            'attributes' => ['enabled' => false],
        ]);

        $this->assertSame('/api/v1', $registry->getGroup('api')->prefix);
        $this->assertCount(1, $registry->getGroup('api')->routes);
    }

    public function test_loads_outgoing_from_config(): void
    {
        $registry = new ConfigRegistry(new AttributeScanner);
        $registry->boot([
            'outgoing' => ['ext' => ['base_url' => 'http://ext', 'timeout' => 15]],
            'attributes' => ['enabled' => false],
        ]);

        $this->assertSame(15, $registry->getOutgoing('ext')->timeout);
    }

    public function test_fluent_registration_overwrites_config(): void
    {
        $registry = new ConfigRegistry(new AttributeScanner);
        $registry->boot([
            'services' => ['users' => ['base_url' => 'http://old']],
            'attributes' => ['enabled' => false],
        ]);

        $registry->registerService(new ServiceDefinition(name: 'users', baseUrl: 'http://new'));

        $this->assertSame('http://new', $registry->getService('users')->baseUrl);
    }

    public function test_get_missing_service_throws(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->registry->getService('nope');
    }

    public function test_get_missing_group_throws(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->registry->getGroup('nope');
    }

    public function test_get_missing_outgoing_throws(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->registry->getOutgoing('nope');
    }

    public function test_has_methods(): void
    {
        $this->registry->registerService(new ServiceDefinition(name: 'svc', baseUrl: 'http://svc'));

        $this->assertTrue($this->registry->hasService('svc'));
        $this->assertFalse($this->registry->hasService('nope'));
    }

    public function test_all_methods(): void
    {
        $this->registry->registerService(new ServiceDefinition(name: 'a', baseUrl: 'http://a'));
        $this->registry->registerService(new ServiceDefinition(name: 'b', baseUrl: 'http://b'));
        $this->registry->registerOutgoing(new OutgoingServiceDefinition(name: 'o', baseUrl: 'http://o'));

        $this->assertCount(2, $this->registry->allServices());
        $this->assertCount(1, $this->registry->allOutgoing());
    }

    public function test_boot_only_runs_once(): void
    {
        $registry = new ConfigRegistry(new AttributeScanner);
        $registry->boot(['services' => ['a' => ['base_url' => 'http://a']], 'attributes' => ['enabled' => false]]);
        $registry->boot(['services' => ['b' => ['base_url' => 'http://b']], 'attributes' => ['enabled' => false]]);

        $this->assertTrue($registry->hasService('a'));
        $this->assertFalse($registry->hasService('b'));
    }
}
