<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\TrackingConfig;
use Lararoxy\LararoxyManager;
use Lararoxy\Outgoing\OutgoingJob;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Tests\TestCase;

class OutgoingRequestTest extends TestCase
{
    public function test_outgoing_sync_request_via_manager(): void
    {
        Http::fake(['http://pay/*' => Http::response(['charged' => true], 200)]);

        $this->registerOutgoing('pay', 'http://pay');

        $response = $this->app->make(LararoxyManager::class)->outgoing('pay')->post('/charge', ['amount' => 5000]);

        $this->assertTrue($response->successful());
        $this->assertNotNull($response->trackingId());
        $this->assertStringStartsWith('trk_', $response->trackingId());
        $this->assertDatabaseHas('tracked_requests', ['tracking_id' => $response->trackingId(), 'service' => 'pay']);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/charge'));
    }

    public function test_outgoing_untracked_skips_database(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $this->registerOutgoing('analytics', 'http://analytics');

        $response = $this->app->make(LararoxyManager::class)->outgoing('analytics')->untracked()->post('/event');

        $this->assertNull($response->trackingId());
        $this->assertDatabaseCount('tracked_requests', 0);
    }

    public function test_outgoing_queued_dispatches_job(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('ok')]);

        $this->registerOutgoing('svc', 'http://svc');

        $manager = $this->app->make(LararoxyManager::class);
        $response = $manager->outgoing('svc')->queued('sync', 'default')->post('/data', ['x' => 1]);

        $this->assertNotNull($response->trackingId());
        Queue::assertPushed(OutgoingJob::class);
    }

    public function test_outgoing_with_bearer_auth(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $registry = $this->app->make(ConfigRegistry::class);
        $registry->registerOutgoing(new OutgoingServiceDefinition(
            name: 'authed', baseUrl: 'http://authed',
            auth: ['type' => 'bearer', 'token' => 'secret-tok'],
            tracking: new TrackingConfig,
        ));

        $this->app->make(LararoxyManager::class)->outgoing('authed')->get('/check');

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer secret-tok'));
    }

    public function test_outgoing_all_http_methods(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $this->registerOutgoing('svc', 'http://svc');
        $manager = $this->app->make(LararoxyManager::class);

        $this->assertSame(200, $manager->outgoing('svc')->get('/a')->status());
        $this->assertSame(200, $manager->outgoing('svc')->put('/b', ['x' => 1])->status());
        $this->assertSame(200, $manager->outgoing('svc')->patch('/c', ['x' => 1])->status());
        $this->assertSame(200, $manager->outgoing('svc')->delete('/d')->status());
    }

    public function test_outgoing_response_exposes_all_data(): void
    {
        Http::fake(['*' => Http::response(['id' => 42], 201, ['X-Custom' => 'val'])]);

        $this->registerOutgoing('svc', 'http://svc');

        $response = $this->app->make(LararoxyManager::class)->outgoing('svc')->post('/create');

        $this->assertSame(201, $response->status());
        $this->assertSame(42, $response->json('id'));
        $this->assertTrue($response->successful());
        $this->assertFalse($response->failed());
        $this->assertNotEmpty($response->body());
        $this->assertNotEmpty($response->headers());
        $this->assertNotNull($response->response());
    }

    public function test_outgoing_failed_request_stores_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->registerOutgoing('fail', 'http://fail');

        try {
            $this->app->make(LararoxyManager::class)->outgoing('fail')->post('/fail');
            $this->fail('Expected exception');
        } catch (\Exception) {
            $this->assertDatabaseHas('tracked_requests', ['service' => 'fail', 'status' => 'failed_to_send']);
        }
    }

    public function test_define_outgoing_via_fluent_api(): void
    {
        $manager = $this->app->make(LararoxyManager::class);
        $manager->defineOutgoing('fluent-svc')->baseUrl('http://fluent')->timeout(15);
        $manager->finalize();

        $this->assertTrue($manager->registry()->hasOutgoing('fluent-svc'));
        $this->assertSame(15, $manager->registry()->getOutgoing('fluent-svc')->timeout);
    }

    private function registerOutgoing(string $name, string $baseUrl): void
    {
        $this->app->make(ConfigRegistry::class)->registerOutgoing(
            new OutgoingServiceDefinition(name: $name, baseUrl: $baseUrl, tracking: new TrackingConfig),
        );
    }
}
