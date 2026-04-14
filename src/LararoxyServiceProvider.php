<?php

declare(strict_types=1);

namespace Lararoxy;

use Illuminate\Support\ServiceProvider;
use Lararoxy\Console\CleanupLogsCommand;
use Lararoxy\Console\CleanupTrackingCommand;
use Lararoxy\Console\ExpireTrackingCommand;
use Lararoxy\Console\ListRoutesCommand;
use Lararoxy\Console\ListServicesCommand;
use Lararoxy\Contracts\LogDriverContract;
use Lararoxy\Contracts\TrackingIdGenerator;
use Lararoxy\Contracts\TrackingStoreContract;
use Lararoxy\Enums\LogDriverType;
use Lararoxy\Enums\TrackingStoreType;
use Lararoxy\Http\CircuitBreakerManager;
use Lararoxy\Http\ProxyHttpClient;
use Lararoxy\Http\RouteRegistrar;
use Lararoxy\Logging\DatabaseLogDriver;
use Lararoxy\Logging\FieldRedactor;
use Lararoxy\Logging\FileLogDriver;
use Lararoxy\Logging\LogSampler;
use Lararoxy\Logging\RequestLogger;
use Lararoxy\Outgoing\OutgoingDispatcher;
use Lararoxy\Support\AttributeScanner;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tracking\CacheTrackingStore;
use Lararoxy\Tracking\DatabaseTrackingStore;
use Lararoxy\Tracking\DefaultTrackingIdGenerator;

class LararoxyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lararoxy.php', 'lararoxy');

        $this->app->singleton(AttributeScanner::class);

        $this->app->singleton(ConfigRegistry::class, function ($app) {
            return new ConfigRegistry($app->make(AttributeScanner::class));
        });

        $this->app->singleton(LararoxyManager::class, function ($app) {
            return new LararoxyManager($app, $app->make(ConfigRegistry::class));
        });

        $this->app->alias(LararoxyManager::class, 'lararoxy');

        $this->registerTrackingBindings();
        $this->registerLoggingBindings();
        $this->registerHttpBindings();
        $this->registerOutgoingBindings();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/lararoxy.php' => config_path('lararoxy.php'),
            ], 'lararoxy-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'lararoxy-migrations');

            $this->commands([
                CleanupLogsCommand::class,
                CleanupTrackingCommand::class,
                ExpireTrackingCommand::class,
                ListRoutesCommand::class,
                ListServicesCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->bootRegistry();
        $this->loadRoutesFromProxy();
        $this->app->make(LararoxyManager::class)->finalize();
        $this->registerProxyRoutes();
    }

    protected function bootRegistry(): void
    {
        $registry = $this->app->make(ConfigRegistry::class);
        $config = $this->app->make('config')->get('lararoxy', []);

        $registry->boot($config);
    }

    protected function loadRoutesFromProxy(): void
    {
        $files = config('lararoxy.routes.files', [base_path('routes/proxy.php')]);

        foreach ($files as $file) {
            if (file_exists($file)) {
                require $file;
            }
        }
    }

    protected function registerProxyRoutes(): void
    {
        $registrar = new RouteRegistrar($this->app->make(ConfigRegistry::class));
        $registrar->register();
        $registrar->registerWebhooks();
    }

    protected function registerTrackingBindings(): void
    {
        $this->app->singleton(TrackingIdGenerator::class, DefaultTrackingIdGenerator::class);

        $this->app->singleton(TrackingStoreContract::class, function () {
            $store = config('lararoxy.tracking.default_store', 'database');

            return match (TrackingStoreType::tryFrom($store)) {
                TrackingStoreType::Cache, TrackingStoreType::Redis => new CacheTrackingStore,
                default => new DatabaseTrackingStore,
            };
        });
    }

    protected function registerLoggingBindings(): void
    {
        $this->app->singleton(FieldRedactor::class, function () {
            return new FieldRedactor(
                headerNames: config('lararoxy.logging.redact_headers', []),
                fieldNames: config('lararoxy.logging.redact_fields', []),
            );
        });

        $this->app->singleton(LogSampler::class, function () {
            return new LogSampler(
                enabled: config('lararoxy.logging.sampling.enabled', false),
                rate: (float) config('lararoxy.logging.sampling.rate', 1.0),
            );
        });

        $this->app->singleton(LogDriverContract::class, function () {
            $driver = config('lararoxy.logging.driver', 'database');

            return match (LogDriverType::tryFrom($driver)) {
                LogDriverType::File => new FileLogDriver,
                default => new DatabaseLogDriver,
            };
        });

        $this->app->singleton(RequestLogger::class, function ($app) {
            return new RequestLogger(
                driver: $app->make(LogDriverContract::class),
                redactor: $app->make(FieldRedactor::class),
                sampler: $app->make(LogSampler::class),
                bodySizeLimit: (int) config('lararoxy.logging.body_size_limit', 16384),
                excludePaths: config('lararoxy.logging.exclude_paths', []),
                escalation: config('lararoxy.logging.escalation', []),
            );
        });
    }

    protected function registerHttpBindings(): void
    {
        $this->app->singleton(CircuitBreakerManager::class);

        $this->app->singleton(ProxyHttpClient::class, function ($app) {
            return new ProxyHttpClient($app->make(CircuitBreakerManager::class));
        });
    }

    protected function registerOutgoingBindings(): void
    {
        $this->app->singleton(OutgoingDispatcher::class, function ($app) {
            return new OutgoingDispatcher(
                $app->make(TrackingIdGenerator::class),
                $app->make(TrackingStoreContract::class),
            );
        });
    }
}
