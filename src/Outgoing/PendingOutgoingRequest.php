<?php

declare(strict_types=1);

namespace Lararoxy\Outgoing;

use Lararoxy\Data\OutgoingServiceDefinition;

class PendingOutgoingRequest
{
    protected bool $tracked = true;

    protected bool $queued = false;

    protected ?string $queueConnection = null;

    protected ?string $queueName = null;

    public function __construct(
        protected OutgoingServiceDefinition $service,
        protected OutgoingDispatcher $dispatcher,
    ) {
        // Apply service-level queue config
        if ($service->queue !== null && ($service->queue['enabled'] ?? false)) {
            $this->queued = true;
            $this->queueConnection = $service->queue['connection'] ?? null;
            $this->queueName = $service->queue['queue'] ?? null;
        }
    }

    public function untracked(): static
    {
        $this->tracked = false;

        return $this;
    }

    public function queued(?string $connection = null, ?string $queue = null): static
    {
        $this->queued = true;
        $this->queueConnection = $connection ?? $this->queueConnection;
        $this->queueName = $queue ?? $this->queueName;

        return $this;
    }

    public function get(string $path, array $query = []): OutgoingResponse
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $data = []): OutgoingResponse
    {
        return $this->send('POST', $path, $data);
    }

    public function put(string $path, array $data = []): OutgoingResponse
    {
        return $this->send('PUT', $path, $data);
    }

    public function patch(string $path, array $data = []): OutgoingResponse
    {
        return $this->send('PATCH', $path, $data);
    }

    public function delete(string $path, array $data = []): OutgoingResponse
    {
        return $this->send('DELETE', $path, $data);
    }

    protected function send(string $method, string $path, array $data = []): OutgoingResponse
    {
        return $this->dispatcher->dispatch(
            service: $this->service,
            method: $method,
            path: $path,
            data: $data,
            tracked: $this->tracked,
            queued: $this->queued,
            queueConnection: $this->queueConnection,
            queueName: $this->queueName,
        );
    }
}
