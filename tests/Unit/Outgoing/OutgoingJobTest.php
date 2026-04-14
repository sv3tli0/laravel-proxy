<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Outgoing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Outgoing\OutgoingJob;
use Lararoxy\Tests\TestCase;
use Lararoxy\Tracking\DatabaseTrackingStore;

class OutgoingJobTest extends TestCase
{
    public function test_sends_request_and_updates_tracking(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $store = new DatabaseTrackingStore;
        $store->store('trk_job1', [
            'service' => 'svc', 'method' => 'POST', 'url' => 'http://svc/charge',
            'status' => TrackingStatus::Pending->value,
        ]);

        $job = new OutgoingJob('svc', 'POST', 'http://svc/charge', ['amount' => 100], 'trk_job1');
        $job->handle($store);

        Http::assertSent(fn ($req) => $req->method() === 'POST');
        $this->assertSame(TrackingStatus::Sent->value, $store->find('trk_job1')['status']);
    }

    public function test_sends_all_http_methods(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $store = new DatabaseTrackingStore;

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            (new OutgoingJob('svc', $method, "http://svc/$method", [], null))->handle($store);
        }

        Http::assertSentCount(6);
    }

    public function test_applies_bearer_auth(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $store = new DatabaseTrackingStore;

        $job = new OutgoingJob('svc', 'GET', 'http://svc/a', [], null, auth: ['type' => 'bearer', 'token' => 'secret']);
        $job->handle($store);

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer secret'));
    }

    public function test_applies_api_key_auth(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $store = new DatabaseTrackingStore;

        $job = new OutgoingJob('svc', 'GET', 'http://svc/a', [], null, auth: ['type' => 'api-key', 'key' => 'k', 'header' => 'X-K']);
        $job->handle($store);

        Http::assertSent(fn ($req) => $req->hasHeader('X-K', 'k'));
    }

    public function test_applies_retry_config(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $store = new DatabaseTrackingStore;

        $job = new OutgoingJob('svc', 'POST', 'http://svc/a', [], null, retryTimes: 3, retryDelay: 10);
        $job->handle($store);

        Http::assertSentCount(1);
    }

    public function test_marks_failed_on_connection_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $store = new DatabaseTrackingStore;
        $store->store('trk_fail', ['service' => 'svc', 'method' => 'POST', 'url' => 'http://svc/fail', 'status' => 'pending']);

        try {
            (new OutgoingJob('svc', 'POST', 'http://svc/fail', [], 'trk_fail'))->handle($store);
            $this->fail('Expected exception');
        } catch (\Exception) {
            $this->assertSame('failed_to_send', $store->find('trk_fail')['status']);
        }
    }

    public function test_skips_tracking_when_no_tracking_id(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $store = new DatabaseTrackingStore;

        (new OutgoingJob('svc', 'GET', 'http://svc/ping', [], null))->handle($store);

        $this->assertDatabaseCount('tracked_requests', 0);
    }
}
