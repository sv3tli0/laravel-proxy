<?php

declare(strict_types=1);

namespace Lararoxy\Tests;

use Lararoxy\Facades\Lararoxy;
use Lararoxy\Facades\Proxy;
use Lararoxy\LararoxyServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            LararoxyServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Lararoxy' => Lararoxy::class,
            'Proxy' => Proxy::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Use array cache/queue for tests
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');

        // Disable attribute scanning in tests by default
        $app['config']->set('lararoxy.attributes.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}
