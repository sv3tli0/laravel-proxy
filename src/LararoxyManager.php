<?php

declare(strict_types=1);

namespace Lararoxy;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Lararoxy\Builders\AggregateBuilder;
use Lararoxy\Builders\GroupBuilder;
use Lararoxy\Builders\OutgoingBuilder;
use Lararoxy\Builders\RouteBuilder;
use Lararoxy\Builders\ServiceBuilder;
use Lararoxy\Builders\WebhookGroupBuilder;
use Lararoxy\Outgoing\OutgoingDispatcher;
use Lararoxy\Outgoing\PendingOutgoingRequest;
use Lararoxy\Support\ConfigRegistry;
use Lararoxy\Testing\LararoxyFake;

class LararoxyManager
{
    /** @var array<GroupBuilder> Pending groups from fluent API */
    protected array $pendingGroups = [];

    /** @var array<OutgoingBuilder> Pending outgoing builders from fluent API */
    protected array $pendingOutgoing = [];

    protected ?LararoxyFake $fake = null;

    public function __construct(
        protected Application $app,
        protected ConfigRegistry $registry,
    ) {}

    public function registry(): ConfigRegistry
    {
        return $this->registry;
    }

    // ──────────────────────────────────────────────
    //  Fluent API: Services (config-time)
    // ──────────────────────────────────────────────

    public function service(string $name): ServiceBuilder
    {
        $builder = new ServiceBuilder($name);

        $this->app->terminating(function () use ($builder) {
            $this->registry->registerService($builder->build());
        });

        return $builder;
    }

    // ──────────────────────────────────────────────
    //  Fluent API: Endpoint Groups (config-time)
    // ──────────────────────────────────────────────

    public function group(string $name, Closure $callback): GroupBuilder
    {
        $builder = new GroupBuilder($name);
        $builder->routes($callback);

        $this->pendingGroups[] = $builder;

        return $builder;
    }

    // ──────────────────────────────────────────────
    //  Fluent API: Inline Route Helpers
    // ──────────────────────────────────────────────

    public function get(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return new RouteBuilder('GET', $path, $upstream, $upstreamPath);
    }

    public function post(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return new RouteBuilder('POST', $path, $upstream, $upstreamPath);
    }

    public function put(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return new RouteBuilder('PUT', $path, $upstream, $upstreamPath);
    }

    public function patch(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return new RouteBuilder('PATCH', $path, $upstream, $upstreamPath);
    }

    public function delete(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return new RouteBuilder('DELETE', $path, $upstream, $upstreamPath);
    }

    // ──────────────────────────────────────────────
    //  Fluent API: Aggregation (inside group callbacks)
    // ──────────────────────────────────────────────

    public function aggregate(string $path): AggregateBuilder
    {
        return new AggregateBuilder($path);
    }

    // ──────────────────────────────────────────────
    //  Fluent API: Webhooks (config-time)
    // ──────────────────────────────────────────────

    public function webhooks(Closure $callback): WebhookGroupBuilder
    {
        return new WebhookGroupBuilder($callback);
    }

    // ──────────────────────────────────────────────
    //  Outgoing: Config-time & Runtime
    // ──────────────────────────────────────────────

    /**
     * Define an outgoing service (config-time, in routes/proxy.php).
     */
    public function defineOutgoing(string $name): OutgoingBuilder
    {
        $builder = new OutgoingBuilder($name);
        $this->pendingOutgoing[] = $builder;

        return $builder;
    }

    /**
     * Get a pending outgoing request for runtime dispatch.
     *
     * Usage: Lararoxy::outgoing('payment-gateway')->post('/charges', $data)
     */
    public function outgoing(string $name): PendingOutgoingRequest
    {
        $definition = $this->registry->getOutgoing($name);

        return new PendingOutgoingRequest(
            $definition,
            $this->app->make(OutgoingDispatcher::class),
        );
    }

    // ──────────────────────────────────────────────
    //  Finalization
    // ──────────────────────────────────────────────

    public function finalize(): void
    {
        foreach ($this->pendingGroups as $builder) {
            $this->registry->registerGroup($builder->build());
        }

        foreach ($this->pendingOutgoing as $builder) {
            $this->registry->registerOutgoing($builder->build());
        }

        $this->pendingGroups = [];
        $this->pendingOutgoing = [];
    }

    // ──────────────────────────────────────────────
    //  Testing Helpers
    // ──────────────────────────────────────────────

    /**
     * Replace the manager with a fake for testing.
     *
     * @param  array<string, PromiseInterface>  $responses  Keyed by service name
     */
    public function fake(array $responses = []): LararoxyFake
    {
        // Fake HTTP layer too
        if (! empty($responses)) {
            $fakeMap = [];

            foreach ($responses as $service => $response) {
                if ($this->registry->hasService($service)) {
                    $baseUrl = $this->registry->getService($service)->baseUrl;
                    $fakeMap[rtrim($baseUrl, '/').'/*'] = $response;
                }
                if ($this->registry->hasOutgoing($service)) {
                    $baseUrl = $this->registry->getOutgoing($service)->baseUrl;
                    $fakeMap[rtrim($baseUrl, '/').'/*'] = $response;
                }
            }

            Http::fake($fakeMap);
        } else {
            Http::fake();
        }

        $this->fake = new LararoxyFake;

        return $this->fake;
    }

    /**
     * Create a fake HTTP response.
     */
    public static function response(array|string $body = [], int $status = 200, array $headers = []): PromiseInterface
    {
        return Http::response($body, $status, $headers);
    }

    public function assertSent(string $service, ?Closure $callback = null): void
    {
        $this->ensureFake()->assertSent($service, $callback);
    }

    public function assertNotSent(string $service): void
    {
        $this->ensureFake()->assertNotSent($service);
    }

    public function assertSentCount(string $service, int $count): void
    {
        $this->ensureFake()->assertSentCount($service, $count);
    }

    public function assertOutgoing(string $service, ?Closure $callback = null): void
    {
        $this->ensureFake()->assertOutgoing($service, $callback);
    }

    public function assertTracked(string $trackingId, string $status): void
    {
        $this->ensureFake()->assertTracked($trackingId, $status);
    }

    public function isFaked(): bool
    {
        return $this->fake !== null;
    }

    protected function ensureFake(): LararoxyFake
    {
        if ($this->fake === null) {
            throw new \RuntimeException('Lararoxy is not faked. Call Lararoxy::fake() first.');
        }

        return $this->fake;
    }
}
