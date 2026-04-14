<?php

declare(strict_types=1);

namespace Lararoxy\Outgoing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Lararoxy\Contracts\TrackingIdGenerator;
use Lararoxy\Contracts\TrackingStoreContract;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Events\OutgoingRequestFailed;
use Lararoxy\Events\OutgoingRequestSent;

class OutgoingDispatcher
{
    public function __construct(
        protected TrackingIdGenerator $idGenerator,
        protected TrackingStoreContract $trackingStore,
    ) {}

    public function dispatch(
        OutgoingServiceDefinition $service,
        string $method,
        string $path,
        array $data = [],
        bool $tracked = true,
        bool $queued = false,
        ?string $queueConnection = null,
        ?string $queueName = null,
    ): OutgoingResponse {
        $url = rtrim($service->baseUrl, '/').'/'.ltrim($path, '/');
        $trackingId = $tracked ? $this->idGenerator->generate(
            config('lararoxy.tracking.id_prefix', 'trk_')
        ) : null;

        // Store tracking record before dispatch
        if ($trackingId !== null) {
            $this->trackingStore->store($trackingId, [
                'service' => $service->name,
                'method' => $method,
                'url' => $url,
                'status' => TrackingStatus::Pending->value,
                'request_body' => ! empty($data) ? json_encode($data) : null,
                'expires_at' => $service->tracking?->ttl
                    ? now()->addSeconds($service->tracking->ttl)
                    : null,
            ]);
        }

        if ($queued) {
            // Dispatch via queue — return immediately with tracking ID
            dispatch(new OutgoingJob(
                serviceName: $service->name,
                method: $method,
                url: $url,
                data: $data,
                trackingId: $trackingId,
                timeout: $service->timeout,
                auth: $service->auth,
                retryTimes: $service->retry?->times,
                retryDelay: $service->retry?->delay,
            ))->onConnection($queueConnection)->onQueue($queueName);

            // Return a placeholder response for queued requests
            $pendingResponse = Http::fake([
                '*' => Http::response(['queued' => true, 'tracking_id' => $trackingId], 202),
            ])->get('pending');

            return new OutgoingResponse($pendingResponse, $trackingId);
        }

        // Synchronous dispatch
        return $this->sendSync($service, $method, $url, $data, $trackingId);
    }

    protected function sendSync(
        OutgoingServiceDefinition $service,
        string $method,
        string $url,
        array $data,
        ?string $trackingId,
    ): OutgoingResponse {
        $request = Http::timeout($service->timeout);

        // Apply auth
        if ($service->auth !== null) {
            $request = $this->applyAuth($request, $service->auth);
        }

        // Apply retry
        if ($service->retry !== null) {
            $request = $request->retry($service->retry->times, $service->retry->delay);
        }

        // Add tracking ID header
        if ($trackingId !== null && $service->tracking !== null) {
            $request = $request->withHeaders([
                $service->tracking->idHeader => $trackingId,
            ]);
        }

        try {
            $response = $this->sendRequest($request, $method, $url, $data);

            if ($trackingId !== null) {
                $this->trackingStore->updateStatus($trackingId, TrackingStatus::Sent->value, [
                    'response_status' => $response->status(),
                ]);

                OutgoingRequestSent::dispatch(
                    $service->name, $trackingId, $method, $url, $response->status()
                );
            }

            return new OutgoingResponse($response, $trackingId);
        } catch (\Exception $e) {
            if ($trackingId !== null) {
                $this->trackingStore->updateStatus($trackingId, TrackingStatus::FailedToSend->value, [
                    'error' => $e->getMessage(),
                ]);

                OutgoingRequestFailed::dispatch(
                    $service->name, $trackingId, $method, $url, $e->getMessage()
                );
            }

            throw $e;
        }
    }

    protected function sendRequest(
        PendingRequest $request,
        string $method,
        string $url,
        array $data,
    ): Response {
        return match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => $request->send($method, $url, ['json' => $data]),
        };
    }

    protected function applyAuth(
        PendingRequest $request,
        array $auth,
    ): PendingRequest {
        return match ($auth['type'] ?? '') {
            'bearer' => $request->withToken($auth['token'] ?? ''),
            'api-key' => $request->withHeaders([$auth['header'] ?? 'X-Api-Key' => $auth['key'] ?? '']),
            default => $request,
        };
    }
}
