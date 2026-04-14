# Lararoxy - Implementation Plan

**API Gateway / Proxy package for Laravel 13+**
PHP 8.3+ | Laravel 13 | Sanctum integration | Attributes-first

## Overview

Lararoxy turns a Laravel app into a declarative API gateway. Define endpoint groups, proxy to internal services, authenticate with Sanctum, track outgoing calls, handle callbacks — via config files, Fluent API, or PHP Attributes.

## Plan Documents

| # | Document | Scope |
|---|----------|-------|
| 1 | [Architecture & Patterns](01-architecture.md) | Supported patterns (BFF, Gateway, Strangler Fig, etc.) |
| 2 | [Endpoint Groups & Routes](02-endpoint-groups.md) | Group definitions, route mapping, path rewriting |
| 3 | [Upstream Services](03-upstream-services.md) | Service definitions, retry, circuit breaker, health checks |
| 4 | [Auth & TokenPayload](04-auth-token-payload.md) | Auth schemas, TokenPayload, AuthModel, Sanctum integration |
| 5 | [Request Pipeline](05-request-pipeline.md) | Pipeline stages, custom stages, request flow |
| 6 | [Outgoing Calls & Tracking](06-outgoing-tracking.md) | Outgoing dispatch, tracking IDs, callbacks, state machine |
| 7 | [Request Logging](07-request-logging.md) | Logging levels, sampling, retention, redaction, escalation |
| 8 | [Proxy Schemas](08-proxy-schemas.md) | All incoming/outgoing/callback flow patterns |
| 9 | [Configuration](09-configuration.md) | Config file, Fluent API, PHP Attributes, config reference |
| 10 | [Testing](10-testing.md) | Testbench setup, fakes, assertions |

## Key Decisions

- **Attributes-first config** — precedence: Attributes > Fluent API > Config file
- **`Proxy::`** facade (not `Route::`) — routing-inspired API but fully proxy-specific
- **TokenPayload** bridges Sanctum auth to gateway-level authorization and header injection
- **Tracking IDs** connect outgoing calls to inbound callbacks across any timeframe
- **Request logging** must have strict controls (sampling, retention caps, field redaction) to avoid storage explosion
- **PHP Attributes** are first-class, not an afterthought — both L12 and L13 compatible
