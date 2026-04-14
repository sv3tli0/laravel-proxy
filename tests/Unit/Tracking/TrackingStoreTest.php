<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Tracking;

use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Models\TrackedRequest;
use Lararoxy\Tests\TestCase;
use Lararoxy\Tracking\CacheTrackingStore;
use Lararoxy\Tracking\DatabaseTrackingStore;
use Lararoxy\Tracking\DefaultTrackingIdGenerator;

class TrackingStoreTest extends TestCase
{
    // ── ID Generator ──

    public function test_generates_prefixed_unique_ids(): void
    {
        $gen = new DefaultTrackingIdGenerator;

        $id1 = $gen->generate();
        $id2 = $gen->generate('req_');

        $this->assertStringStartsWith('trk_', $id1);
        $this->assertStringStartsWith('req_', $id2);
        $this->assertNotSame($id1, $id2);
    }

    // ── DatabaseTrackingStore ──

    public function test_db_store_stores_and_finds(): void
    {
        $store = new DatabaseTrackingStore;
        $store->store('trk_1', [
            'service' => 'payments', 'method' => 'POST', 'url' => 'http://pay/charge',
            'status' => TrackingStatus::Pending->value,
        ]);

        $found = $store->find('trk_1');
        $this->assertSame('payments', $found['service']);
    }

    public function test_db_store_returns_null_for_missing(): void
    {
        $this->assertNull((new DatabaseTrackingStore)->find('trk_none'));
    }

    public function test_db_store_updates_status_with_metadata(): void
    {
        $store = new DatabaseTrackingStore;
        $store->store('trk_upd', [
            'service' => 'svc', 'method' => 'GET', 'url' => 'http://svc/a',
            'status' => TrackingStatus::Pending->value,
        ]);

        $store->updateStatus('trk_upd', TrackingStatus::Sent->value, ['response_status' => 200]);

        $found = $store->find('trk_upd');
        $this->assertSame(TrackingStatus::Sent->value, $found['status']);
        $this->assertSame(200, $found['metadata']['response_status']);
    }

    public function test_db_store_update_missing_is_noop(): void
    {
        (new DatabaseTrackingStore)->updateStatus('trk_nope', TrackingStatus::Sent->value);
        $this->assertNull((new DatabaseTrackingStore)->find('trk_nope'));
    }

    public function test_db_store_cleanup_deletes_old_records(): void
    {
        TrackedRequest::create([
            'tracking_id' => 'trk_old', 'service' => 'svc', 'method' => 'POST', 'url' => 'http://a',
            'status' => TrackingStatus::Sent->value,
            'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100),
        ]);
        TrackedRequest::create([
            'tracking_id' => 'trk_new', 'service' => 'svc', 'method' => 'POST', 'url' => 'http://b',
            'status' => TrackingStatus::Sent->value,
        ]);

        $deleted = (new DatabaseTrackingStore)->cleanup(30);

        $this->assertSame(1, $deleted);
        $this->assertNotNull((new DatabaseTrackingStore)->find('trk_new'));
    }

    // ── CacheTrackingStore ──

    public function test_cache_store_stores_and_finds(): void
    {
        $store = new CacheTrackingStore;
        $store->store('trk_c1', ['service' => 'svc', 'status' => 'pending']);

        $this->assertSame('svc', $store->find('trk_c1')['service']);
    }

    public function test_cache_store_returns_null_for_missing(): void
    {
        $this->assertNull((new CacheTrackingStore)->find('trk_nope'));
    }

    public function test_cache_store_updates_status(): void
    {
        $store = new CacheTrackingStore;
        $store->store('trk_cu', ['service' => 'svc', 'status' => 'pending']);
        $store->updateStatus('trk_cu', 'sent', ['extra' => 'data']);

        $found = $store->find('trk_cu');
        $this->assertSame('sent', $found['status']);
        $this->assertSame('data', $found['metadata']['extra']);
    }

    public function test_cache_store_update_missing_is_noop(): void
    {
        (new CacheTrackingStore)->updateStatus('trk_nope', 'sent');
        $this->assertNull((new CacheTrackingStore)->find('trk_nope'));
    }

    public function test_cache_store_cleanup_returns_zero(): void
    {
        $this->assertSame(0, (new CacheTrackingStore)->cleanup(30));
    }
}
