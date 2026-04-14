<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Lararoxy\Enums\LogLevel;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Models\ProxyLog;
use Lararoxy\Models\TrackedRequest;
use Lararoxy\Tests\TestCase;

class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_proxy_log_can_be_created(): void
    {
        $log = ProxyLog::create([
            'request_id' => 'req_123',
            'group' => 'api',
            'level' => LogLevel::Minimal->value,
            'method' => 'GET',
            'path' => '/api/users/1',
            'status_code' => 200,
            'duration_ms' => 42,
            'created_at' => now(),
        ]);

        $this->assertNotNull($log->id);
        $this->assertSame('api', $log->group);
        $this->assertSame(LogLevel::Minimal, $log->level);
        $this->assertSame(200, $log->status_code);
    }

    public function test_proxy_log_scopes(): void
    {
        ProxyLog::create([
            'request_id' => 'req_1', 'group' => 'api', 'level' => 'minimal',
            'method' => 'GET', 'path' => '/a', 'status_code' => 200, 'created_at' => now(),
        ]);
        ProxyLog::create([
            'request_id' => 'req_2', 'group' => 'admin', 'level' => 'minimal',
            'method' => 'GET', 'path' => '/b', 'status_code' => 500, 'created_at' => now(),
        ]);
        ProxyLog::create([
            'request_id' => 'req_3', 'group' => 'api', 'level' => 'minimal',
            'method' => 'GET', 'path' => '/c', 'status_code' => 200,
            'created_at' => now()->subDays(60),
        ]);

        $this->assertSame(2, ProxyLog::forGroup('api')->count());
        $this->assertSame(1, ProxyLog::withStatus(500)->count());
        $this->assertSame(1, ProxyLog::olderThan(30)->count());
    }

    public function test_tracked_request_can_be_created(): void
    {
        $tracked = TrackedRequest::create([
            'tracking_id' => 'trk_abc123',
            'service' => 'payments',
            'method' => 'POST',
            'url' => 'http://payments/charge',
            'status' => TrackingStatus::Pending->value,
        ]);

        $this->assertNotNull($tracked->id);
        $this->assertSame(TrackingStatus::Pending, $tracked->status);
        $this->assertFalse($tracked->isTerminal());
    }

    public function test_tracked_request_terminal_check(): void
    {
        $tracked = TrackedRequest::create([
            'tracking_id' => 'trk_term',
            'service' => 'svc',
            'method' => 'POST',
            'url' => 'http://svc/a',
            'status' => TrackingStatus::Processed->value,
        ]);

        $this->assertTrue($tracked->isTerminal());
    }

    public function test_tracked_request_expired_check(): void
    {
        $tracked = TrackedRequest::create([
            'tracking_id' => 'trk_exp',
            'service' => 'svc',
            'method' => 'POST',
            'url' => 'http://svc/a',
            'status' => TrackingStatus::Sent->value,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertTrue($tracked->isExpired());
    }

    public function test_tracked_request_not_expired_when_terminal(): void
    {
        $tracked = TrackedRequest::create([
            'tracking_id' => 'trk_done',
            'service' => 'svc',
            'method' => 'POST',
            'url' => 'http://svc/a',
            'status' => TrackingStatus::Processed->value,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertFalse($tracked->isExpired());
    }

    public function test_tracked_request_scopes(): void
    {
        TrackedRequest::create([
            'tracking_id' => 'trk_1', 'service' => 'payments', 'method' => 'POST',
            'url' => 'http://a', 'status' => TrackingStatus::Pending->value,
        ]);
        TrackedRequest::create([
            'tracking_id' => 'trk_2', 'service' => 'email', 'method' => 'POST',
            'url' => 'http://b', 'status' => TrackingStatus::Sent->value,
        ]);
        TrackedRequest::create([
            'tracking_id' => 'trk_3', 'service' => 'payments', 'method' => 'POST',
            'url' => 'http://c', 'status' => TrackingStatus::Sent->value,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertSame(2, TrackedRequest::forService('payments')->count());
        $this->assertSame(1, TrackedRequest::pending()->count());
        $this->assertSame(1, TrackedRequest::expired()->count());
    }
}
