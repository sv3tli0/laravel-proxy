<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Data\TrackingConfig;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Models\ProxyLog;
use Lararoxy\Models\TrackedRequest;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;

class ArtisanCommandTest extends TestCase
{
    public function test_cleanup_logs_removes_old_records(): void
    {
        ProxyLog::create([
            'request_id' => 'req_old', 'group' => 'api', 'level' => 'minimal',
            'method' => 'GET', 'path' => '/a', 'created_at' => now()->subDays(60),
        ]);
        ProxyLog::create([
            'request_id' => 'req_new', 'group' => 'api', 'level' => 'minimal',
            'method' => 'GET', 'path' => '/b', 'created_at' => now(),
        ]);

        $this->artisan('lararoxy:cleanup-logs', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutputToContain('1');

        $this->assertDatabaseMissing('proxy_logs', ['request_id' => 'req_old']);
        $this->assertDatabaseHas('proxy_logs', ['request_id' => 'req_new']);
    }

    public function test_cleanup_logs_with_max_records(): void
    {
        for ($i = 0; $i < 5; $i++) {
            ProxyLog::create([
                'request_id' => "req_$i", 'group' => 'api', 'level' => 'minimal',
                'method' => 'GET', 'path' => '/a', 'created_at' => now(),
            ]);
        }

        $this->artisan('lararoxy:cleanup-logs', ['--days' => 0, '--max' => 2])
            ->assertSuccessful();

        $this->assertLessThanOrEqual(2, ProxyLog::count());
    }

    public function test_cleanup_tracking_removes_old_records(): void
    {
        TrackedRequest::create([
            'tracking_id' => 'trk_old', 'service' => 'svc', 'method' => 'POST', 'url' => 'http://a',
            'status' => TrackingStatus::Sent->value,
            'created_at' => now()->subDays(200), 'updated_at' => now()->subDays(200),
        ]);

        $this->artisan('lararoxy:cleanup-tracking', ['--days' => 90])
            ->assertSuccessful()
            ->expectsOutputToContain('1');
    }

    public function test_expire_tracking_marks_expired_records(): void
    {
        TrackedRequest::create([
            'tracking_id' => 'trk_exp', 'service' => 'svc', 'method' => 'POST', 'url' => 'http://a',
            'status' => TrackingStatus::Sent->value,
            'expires_at' => now()->subHour(),
        ]);
        TrackedRequest::create([
            'tracking_id' => 'trk_ok', 'service' => 'svc', 'method' => 'POST', 'url' => 'http://b',
            'status' => TrackingStatus::Sent->value,
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('lararoxy:expire-tracking')
            ->assertSuccessful()
            ->expectsOutputToContain('1');

        $this->assertSame(TrackingStatus::Expired, TrackedRequest::where('tracking_id', 'trk_exp')->first()->status);
        $this->assertSame(TrackingStatus::Sent, TrackedRequest::where('tracking_id', 'trk_ok')->first()->status);
    }

    public function test_list_routes_shows_registered_routes(): void
    {
        $this->app->make(ConfigRegistry::class)->registerGroup(new GroupDefinition(
            name: 'api',
            prefix: '/api',
            routes: [new RouteDefinition(method: 'GET', path: '/users', upstream: 'users', upstreamPath: '/users')],
        ));

        $this->artisan('lararoxy:routes')->assertSuccessful()->expectsOutputToContain('users');
    }

    public function test_list_routes_filtered_by_group(): void
    {
        $registry = $this->app->make(ConfigRegistry::class);
        $registry->registerGroup(new GroupDefinition(name: 'api', routes: [
            new RouteDefinition(method: 'GET', path: '/users', upstream: 'svc', upstreamPath: '/u'),
        ]));
        $registry->registerGroup(new GroupDefinition(name: 'admin', routes: [
            new RouteDefinition(method: 'GET', path: '/stats', upstream: 'svc', upstreamPath: '/s'),
        ]));

        $this->artisan('lararoxy:routes', ['group' => 'api'])->assertSuccessful()->expectsOutputToContain('users');
    }

    public function test_list_routes_empty(): void
    {
        $this->artisan('lararoxy:routes')->assertSuccessful()->expectsOutputToContain('No proxy routes');
    }

    public function test_list_routes_nonexistent_group(): void
    {
        $this->app->make(ConfigRegistry::class)->registerGroup(new GroupDefinition(name: 'api', routes: [
            new RouteDefinition(method: 'GET', path: '/x', upstream: 's', upstreamPath: '/x'),
        ]));

        $this->artisan('lararoxy:routes', ['group' => 'nope'])->assertSuccessful()->expectsOutputToContain('No routes found');
    }

    public function test_list_services(): void
    {
        $registry = $this->app->make(ConfigRegistry::class);
        $registry->registerService(new ServiceDefinition(name: 'users-svc', baseUrl: 'http://users'));
        $registry->registerOutgoing(new OutgoingServiceDefinition(name: 'payments', baseUrl: 'http://pay', tracking: new TrackingConfig));

        $this->artisan('lararoxy:services')
            ->assertSuccessful()
            ->expectsOutputToContain('users-svc')
            ->expectsOutputToContain('payments');
    }

    public function test_list_services_empty(): void
    {
        $this->artisan('lararoxy:services')->assertSuccessful()->expectsOutputToContain('No services');
    }
}
