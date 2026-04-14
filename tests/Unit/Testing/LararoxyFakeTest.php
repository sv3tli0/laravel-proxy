<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Lararoxy\LararoxyManager;
use Lararoxy\Testing\LararoxyFake;
use Lararoxy\Tests\TestCase;

class LararoxyFakeTest extends TestCase
{
    public function test_assert_sent_passes_when_recorded(): void
    {
        $fake = new LararoxyFake;
        $fake->recordSent('users', 'GET', '/users/1');

        $fake->assertSent('users');
    }

    public function test_assert_sent_with_callback_matches(): void
    {
        $fake = new LararoxyFake;
        $fake->recordSent('users', 'GET', '/users/42');

        $fake->assertSent('users', fn ($req) => $req->url === '/users/42');
    }

    public function test_assert_not_sent(): void
    {
        $fake = new LararoxyFake;

        $fake->assertNotSent('users');
    }

    public function test_assert_sent_count(): void
    {
        $fake = new LararoxyFake;
        $fake->recordSent('users', 'GET', '/a');
        $fake->recordSent('users', 'GET', '/b');
        $fake->recordSent('users', 'GET', '/c');

        $fake->assertSentCount('users', 3);
    }

    public function test_assert_outgoing_with_body_callback(): void
    {
        $fake = new LararoxyFake;
        $fake->recordOutgoing('payments', 'POST', '/charge', ['amount' => 5000]);

        $fake->assertOutgoing('payments', fn ($req) => $req->body['amount'] === 5000);
    }

    public function test_assert_tracked(): void
    {
        $fake = new LararoxyFake;
        $fake->recordTracking('trk_abc', 'processed');

        $fake->assertTracked('trk_abc', 'processed');
    }

    public function test_response_helper(): void
    {
        $response = LararoxyFake::response(['ok' => true], 201);

        $this->assertInstanceOf(PromiseInterface::class, $response);
    }

    public function test_manager_fake_delegates_to_fake_instance(): void
    {
        $manager = $this->app->make(LararoxyManager::class);
        $fake = $manager->fake();

        $fake->recordSent('users', 'GET', '/users/1');
        $manager->assertSent('users', fn ($req) => $req->url === '/users/1');
        $manager->assertNotSent('orders');
        $manager->assertSentCount('users', 1);

        $this->assertTrue($manager->isFaked());
    }

    public function test_manager_assert_throws_when_not_faked(): void
    {
        $manager = $this->app->make(LararoxyManager::class);

        $this->expectException(\RuntimeException::class);
        $manager->assertSent('users');
    }

    public function test_manager_response_static_helper(): void
    {
        $response = LararoxyManager::response(['id' => 1], 201);

        $this->assertInstanceOf(PromiseInterface::class, $response);
    }
}
