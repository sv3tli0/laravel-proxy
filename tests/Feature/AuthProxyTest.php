<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Lararoxy\Contracts\AuthModel;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Http\RouteRegistrar;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;
use Laravel\Sanctum\SanctumServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Models\UserTokenPayload;

class AuthProxyTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            SanctumServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $registry = $this->app->make(ConfigRegistry::class);

        $registry->registerService(new ServiceDefinition(
            name: 'backend',
            baseUrl: 'http://backend',
        ));

        $registry->registerGroup(new GroupDefinition(
            name: 'secure-api',
            prefix: '/secure',
            auth: ['driver' => 'api-key', 'header' => 'X-Api-Key'],
            routes: [
                new RouteDefinition(method: 'GET', path: '/data', upstream: 'backend', upstreamPath: '/data'),
            ],
        ));

        $registry->registerGroup(new GroupDefinition(
            name: 'public-api',
            prefix: '/public',
            routes: [
                new RouteDefinition(method: 'GET', path: '/info', upstream: 'backend', upstreamPath: '/info'),
            ],
        ));

        $registrar = new RouteRegistrar($registry);
        $registrar->register();
    }

    public function test_unauthenticated_request_to_secure_group_returns_401(): void
    {
        $response = $this->getJson('/secure/data');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthenticated']);
    }

    public function test_public_group_allows_unauthenticated_access(): void
    {
        Http::fake(['http://backend/*' => Http::response(['info' => 'public'], 200)]);

        $response = $this->getJson('/public/info');

        $response->assertOk();
        $response->assertJson(['info' => 'public']);
    }

    public function test_proxy_logs_contain_group_and_method(): void
    {
        Http::fake(['http://backend/*' => Http::response('ok', 200)]);

        $this->getJson('/public/info');

        $this->assertDatabaseHas('proxy_logs', [
            'group' => 'public-api',
            'method' => 'GET',
        ]);
    }

    public function test_workbench_user_factory(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertNotNull($user->email);
        $this->assertInstanceOf(AuthModel::class, $user);
    }

    public function test_workbench_user_has_token_payload_class(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            UserTokenPayload::class,
            $user->tokenPayloadClass(),
        );
    }
}
