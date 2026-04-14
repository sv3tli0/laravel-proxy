<?php

declare(strict_types=1);

namespace Lararoxy\Outgoing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Lararoxy\Contracts\TrackingStoreContract;
use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Events\OutgoingRequestFailed;
use Lararoxy\Events\OutgoingRequestSent;

class OutgoingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $serviceName,
        public readonly string $method,
        public readonly string $url,
        public readonly array $data,
        public readonly ?string $trackingId,
        public readonly int $timeout = 30,
        public readonly ?array $auth = null,
        public readonly ?int $retryTimes = null,
        public readonly ?int $retryDelay = null,
    ) {}

    public function handle(TrackingStoreContract $trackingStore): void
    {
        $request = Http::timeout($this->timeout);

        if ($this->auth !== null) {
            $request = match ($this->auth['type'] ?? '') {
                'bearer' => $request->withToken($this->auth['token'] ?? ''),
                'api-key' => $request->withHeaders([
                    $this->auth['header'] ?? 'X-Api-Key' => $this->auth['key'] ?? '',
                ]),
                default => $request,
            };
        }

        if ($this->retryTimes !== null) {
            $request = $request->retry($this->retryTimes, $this->retryDelay ?? 1000);
        }

        try {
            $response = match (strtoupper($this->method)) {
                'GET' => $request->get($this->url, $this->data),
                'POST' => $request->post($this->url, $this->data),
                'PUT' => $request->put($this->url, $this->data),
                'PATCH' => $request->patch($this->url, $this->data),
                'DELETE' => $request->delete($this->url, $this->data),
                default => $request->send($this->method, $this->url, ['json' => $this->data]),
            };

            if ($this->trackingId !== null) {
                $trackingStore->updateStatus($this->trackingId, TrackingStatus::Sent->value, [
                    'response_status' => $response->status(),
                ]);

                OutgoingRequestSent::dispatch(
                    $this->serviceName, $this->trackingId, $this->method, $this->url, $response->status()
                );
            }
        } catch (\Exception $e) {
            if ($this->trackingId !== null) {
                $trackingStore->updateStatus($this->trackingId, TrackingStatus::FailedToSend->value, [
                    'error' => $e->getMessage(),
                ]);

                OutgoingRequestFailed::dispatch(
                    $this->serviceName, $this->trackingId, $this->method, $this->url, $e->getMessage()
                );
            }

            throw $e;
        }
    }
}
