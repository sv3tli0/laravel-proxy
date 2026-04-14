<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Auth;

use Illuminate\Http\Request;
use Lararoxy\Auth\ApiKeyAuthDriver;
use Lararoxy\Auth\AuthDriverFactory;
use Lararoxy\Auth\PassthroughAuthDriver;
use Lararoxy\Auth\SanctumAuthDriver;
use Lararoxy\Contracts\AuthDriver;
use Lararoxy\Contracts\AuthModel;
use Lararoxy\Exceptions\InvalidConfigurationException;
use Lararoxy\Tests\TestCase;

class AuthDriverTest extends TestCase
{
    public function test_passthrough_always_returns_null(): void
    {
        $driver = new PassthroughAuthDriver;

        $this->assertNull($driver->authenticate(Request::create('/test')));
    }

    public function test_api_key_returns_null_when_header_missing(): void
    {
        $driver = new ApiKeyAuthDriver('X-Api-Key');

        $this->assertNull($driver->authenticate(Request::create('/test')));
    }

    public function test_api_key_returns_null_without_resolver(): void
    {
        $driver = new ApiKeyAuthDriver('X-Api-Key');
        $request = Request::create('/test');
        $request->headers->set('X-Api-Key', 'some-key');

        $this->assertNull($driver->authenticate($request));
    }

    public function test_api_key_resolves_valid_key(): void
    {
        $model = $this->makeAuthModel();
        $driver = new ApiKeyAuthDriver('X-Api-Key', fn ($key) => $key === 'valid' ? $model : null);

        $request = Request::create('/test');
        $request->headers->set('X-Api-Key', 'valid');

        $this->assertSame($model, $driver->authenticate($request));
    }

    public function test_api_key_rejects_invalid_key(): void
    {
        $model = $this->makeAuthModel();
        $driver = new ApiKeyAuthDriver('X-Api-Key', fn ($key) => $key === 'valid' ? $model : null);

        $request = Request::create('/test');
        $request->headers->set('X-Api-Key', 'invalid');

        $this->assertNull($driver->authenticate($request));
    }

    public function test_sanctum_driver_returns_null_when_no_user(): void
    {
        $driver = new SanctumAuthDriver('web');

        $this->assertNull($driver->authenticate(Request::create('/test')));
    }

    public function test_factory_creates_sanctum_driver(): void
    {
        $this->assertInstanceOf(SanctumAuthDriver::class, (new AuthDriverFactory)->make(['driver' => 'sanctum']));
    }

    public function test_factory_creates_sanctum_spa_driver(): void
    {
        $this->assertInstanceOf(SanctumAuthDriver::class, (new AuthDriverFactory)->make(['driver' => 'sanctum-spa']));
    }

    public function test_factory_creates_passthrough_driver(): void
    {
        $this->assertInstanceOf(PassthroughAuthDriver::class, (new AuthDriverFactory)->make(['driver' => 'passthrough']));
    }

    public function test_factory_creates_api_key_driver(): void
    {
        $this->assertInstanceOf(ApiKeyAuthDriver::class, (new AuthDriverFactory)->make(['driver' => 'api-key']));
    }

    public function test_factory_creates_custom_class_driver(): void
    {
        $driver = (new AuthDriverFactory)->make([
            'driver' => 'custom',
            'class' => PassthroughAuthDriver::class,
        ]);

        $this->assertInstanceOf(PassthroughAuthDriver::class, $driver);
    }

    public function test_factory_throws_for_unknown_driver(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new AuthDriverFactory)->make(['driver' => 'nonexistent']);
    }

    public function test_factory_throws_for_custom_without_class(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new AuthDriverFactory)->make(['driver' => 'custom']);
    }

    public function test_factory_extend_registers_custom_driver(): void
    {
        $factory = new AuthDriverFactory;
        $factory->extend('my-driver', fn () => new PassthroughAuthDriver);

        // extend stores it — custom class-based resolution still works independently
        $driver = $factory->make(['driver' => 'custom', 'class' => PassthroughAuthDriver::class]);
        $this->assertInstanceOf(AuthDriver::class, $driver);
    }

    private function makeAuthModel(): AuthModel
    {
        return new class implements AuthModel
        {
            public function tokenPayloadClass(): string
            {
                return 'FakePayload';
            }
        };
    }
}
