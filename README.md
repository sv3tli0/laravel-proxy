# Lararoxy

A Laravel package for declarative API proxying, gateway management, and service-to-service communication.

**Requires:** PHP 8.3+ | Laravel 13

Lararoxy turns your Laravel application into a configurable API gateway — define endpoint groups, proxy to internal services, authenticate with Sanctum, track outgoing calls, and handle callbacks.

## Features

- **Endpoint Groups** — Named collections of proxy routes with shared auth, middleware, and logging
- **Path Mapping & Rewriting** — `{id}` from URL, `{token.user_id}` from authenticated context
- **Upstream Services** — Retry policies, circuit breakers, health checks per backend
- **TokenPayload** — Structured auth data bridging Sanctum to gateway-level authorization
- **Request Pipeline** — 11-stage configurable pipeline with custom stage support
- **Outgoing Calls** — Tracked HTTP calls with unique tracking IDs for callback matching
- **Callback Handling** — Match inbound webhooks to outgoing requests, verify signatures
- **Request Logging** — Granular controls: sampling, retention caps, body limits, field redaction
- **Attributes-First Config** — PHP Attributes as primary, Fluent `Proxy::` API and config file as overrides

## Supported Patterns

API Gateway, Backend for Frontend (BFF), Reverse Proxy, API Aggregation, Anti-Corruption Layer, Ambassador, Strangler Fig, Edge Service, Service Facade, API Versioning Gateway.

## Quick Start

```bash
composer require lararoxy/lararoxy
php artisan vendor:publish --provider="Lararoxy\LararoxyServiceProvider" --tag="config"
php artisan migrate
```

```php
// routes/proxy.php
Proxy::service('backend')->baseUrl(env('BACKEND_URL'));

Proxy::group('api', function () {
    Proxy::get('/users/{id}', 'backend', '/api/users/{id}');
})->prefix('/proxy')->auth('sanctum');
```

## Documentation

See [docs/PLAN.md](docs/PLAN.md) for the full implementation plan and all detailed specifications:

| Document | Scope |
|----------|-------|
| [Architecture & Patterns](docs/01-architecture.md) | Supported patterns and when to use them |
| [Endpoint Groups & Routes](docs/02-endpoint-groups.md) | Group config, route mapping, path variables |
| [Upstream Services](docs/03-upstream-services.md) | Service definitions, retry, circuit breaker |
| [Auth & TokenPayload](docs/04-auth-token-payload.md) | Auth schemas, TokenPayload, Sanctum integration |
| [Request Pipeline](docs/05-request-pipeline.md) | Pipeline stages, custom stages |
| [Outgoing Calls & Tracking](docs/06-outgoing-tracking.md) | Tracked dispatch, callbacks, state machine |
| [Request Logging](docs/07-request-logging.md) | Levels, sampling, retention, redaction |
| [Proxy Schemas](docs/08-proxy-schemas.md) | All incoming/outgoing/callback flow patterns |
| [Configuration](docs/09-configuration.md) | Config file, Fluent API, Attributes, full reference |
| [Testing](docs/10-testing.md) | Testbench, fakes, assertions |

## License

MIT
