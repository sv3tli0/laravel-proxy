<?php

declare(strict_types=1);

namespace Lararoxy\Outgoing;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lararoxy\Contracts\CallbackHandler;
use Lararoxy\Contracts\SignatureVerifier;
use Lararoxy\Contracts\TrackingStoreContract;
use Lararoxy\Data\CallbackConfig;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Events\CallbackReceived;
use Lararoxy\Events\CallbackVerificationFailed;
use Lararoxy\Events\TrackingCompleted;

class CallbackRouter
{
    public function __construct(
        protected TrackingStoreContract $trackingStore,
    ) {}

    /**
     * Handle an incoming callback request.
     */
    public function handle(
        Request $request,
        string $trackingId,
        string $serviceName,
        CallbackConfig $config,
    ): Response {
        // Find the tracked request
        $tracked = $this->trackingStore->find($trackingId);

        if ($tracked === null) {
            return new Response(
                json_encode(['error' => 'Tracking ID not found']),
                404,
                ['Content-Type' => 'application/json'],
            );
        }

        CallbackReceived::dispatch($trackingId, $serviceName, $request);

        // Verify signature if configured
        if ($config->signatureHeader !== null && $config->signatureVerifier !== null) {
            if (! $this->verifySignature($request, $config)) {
                CallbackVerificationFailed::dispatch($trackingId, $serviceName, $request);

                // Notify the handler of verification failure
                if ($config->handler !== null) {
                    $handler = app($config->handler);

                    if ($handler instanceof CallbackHandler) {
                        $handler->onVerificationFailed($request);
                    }
                }

                return new Response(
                    json_encode(['error' => 'Signature verification failed']),
                    403,
                    ['Content-Type' => 'application/json'],
                );
            }
        }

        // Update tracking status
        $this->trackingStore->updateStatus($trackingId, TrackingStatus::CallbackReceived->value, [
            'callback_payload' => $request->all(),
        ]);

        // Dispatch to handler
        if ($config->handler !== null) {
            $handler = app($config->handler);

            if ($handler instanceof CallbackHandler) {
                $handler->handle($request, (object) $tracked);
            }
        }

        // Mark as processed
        $this->trackingStore->updateStatus($trackingId, TrackingStatus::Processed->value);

        TrackingCompleted::dispatch($trackingId, $serviceName, TrackingStatus::Processed);

        return new Response(
            json_encode(['status' => 'processed']),
            200,
            ['Content-Type' => 'application/json'],
        );
    }

    protected function verifySignature(Request $request, CallbackConfig $config): bool
    {
        if ($config->signatureVerifier === null || ! class_exists($config->signatureVerifier)) {
            return true;
        }

        $verifier = app($config->signatureVerifier);

        if (! $verifier instanceof SignatureVerifier) {
            return true;
        }

        $secret = config("lararoxy.outgoing.{$config->handler}.signature_secret", '');

        return $verifier->verify($request, $secret);
    }
}
