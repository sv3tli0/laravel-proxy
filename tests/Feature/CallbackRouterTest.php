<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Feature;

use Illuminate\Http\Request;
use Lararoxy\Contracts\CallbackHandler;
use Lararoxy\Contracts\SignatureVerifier;
use Lararoxy\Data\CallbackConfig;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Outgoing\CallbackRouter;
use Lararoxy\Tests\TestCase;
use Lararoxy\Tracking\DatabaseTrackingStore;

class CallbackRouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_returns_404_for_unknown_tracking_id(): void
    {
        $router = new CallbackRouter(new DatabaseTrackingStore);
        $request = Request::create('/webhook', 'POST', ['status' => 'paid']);
        $config = new CallbackConfig(path: '/webhooks/{tracking_id}');

        $response = $router->handle($request, 'trk_unknown', 'payments', $config);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_processes_callback_successfully(): void
    {
        $store = new DatabaseTrackingStore;
        $store->store('trk_cb1', [
            'service' => 'payments',
            'method' => 'POST',
            'url' => 'http://pay/charge',
            'status' => TrackingStatus::Sent->value,
        ]);

        $router = new CallbackRouter($store);
        $request = Request::create('/webhook', 'POST', ['status' => 'paid']);
        $config = new CallbackConfig(path: '/webhooks/{tracking_id}');

        $response = $router->handle($request, 'trk_cb1', 'payments', $config);

        $this->assertSame(200, $response->getStatusCode());

        $tracked = $store->find('trk_cb1');
        $this->assertSame(TrackingStatus::Processed->value, $tracked['status']);
    }

    public function test_processes_callback_with_handler(): void
    {
        $store = new DatabaseTrackingStore;
        $store->store('trk_cb2', [
            'service' => 'payments',
            'method' => 'POST',
            'url' => 'http://pay/charge',
            'status' => TrackingStatus::Sent->value,
        ]);

        // Register a test handler
        $handlerCalled = false;
        $this->app->bind(TestCallbackHandler::class, function () use (&$handlerCalled) {
            return new TestCallbackHandler($handlerCalled);
        });

        $router = new CallbackRouter($store);
        $request = Request::create('/webhook', 'POST', ['status' => 'paid']);
        $config = new CallbackConfig(
            path: '/webhooks/{tracking_id}',
            handler: TestCallbackHandler::class,
        );

        $response = $router->handle($request, 'trk_cb2', 'payments', $config);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handlerCalled);
    }

    public function test_signature_verification_failure(): void
    {
        $store = new DatabaseTrackingStore;
        $store->store('trk_cb3', [
            'service' => 'payments',
            'method' => 'POST',
            'url' => 'http://pay/charge',
            'status' => TrackingStatus::Sent->value,
        ]);

        // Register a verifier that always fails
        $this->app->bind(AlwaysFailVerifier::class, fn () => new AlwaysFailVerifier);

        $router = new CallbackRouter($store);
        $request = Request::create('/webhook', 'POST');
        $config = new CallbackConfig(
            path: '/webhooks/{tracking_id}',
            signatureHeader: 'X-Signature',
            signatureVerifier: AlwaysFailVerifier::class,
        );

        $response = $router->handle($request, 'trk_cb3', 'payments', $config);

        $this->assertSame(403, $response->getStatusCode());
    }
}

class TestCallbackHandler implements CallbackHandler
{
    public function __construct(public bool &$called = false) {}

    public function handle(Request $request, object $trackedRequest): void
    {
        $this->called = true;
    }

    public function onVerificationFailed(Request $request): void {}
}

class AlwaysFailVerifier implements SignatureVerifier
{
    public function verify(Request $request, string $secret): bool
    {
        return false;
    }
}
