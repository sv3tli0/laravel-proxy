# 3. Upstream Services

Upstream services are the backend targets that routes forward to. Each has its own connection, auth, resilience, and health config.

## Service Config Keys

| Key | Type | Description |
|-----|------|-------------|
| `base_url` | string | Service base URL (supports `env()`) |
| `timeout` | int | Request timeout in seconds |
| `connect_timeout` | int | Connection timeout in seconds |
| `retry` | array | `times`, `delay` (ms), `multiplier`, `on` (status codes) |
| `circuit_breaker` | array | `enabled`, `threshold` (failures), `timeout` (seconds to half-open) |
| `health_check` | array | `path`, `interval` (seconds) |
| `auth` | array | Service-to-service auth: `type` + driver-specific keys |

## Retry Policy

Configurable per-service with exponential backoff:

```
Attempt 1 → 503 → wait 100ms → Attempt 2 → 502 → wait 200ms → Attempt 3 → 200 OK
```

Only retries on configured status codes (default: 500, 502, 503, 504).

## Circuit Breaker

Protects downstream services from cascading failures:

```
CLOSED → (threshold failures) → OPEN → (timeout expires) → HALF-OPEN → (success) → CLOSED
                                                                      → (failure) → OPEN
```

## Service-to-Service Auth Types

| Type | Description |
|------|-------------|
| `bearer` | Static or refreshable token in `Authorization` header |
| `hmac` | Request signing with configurable algorithm |
| `api-key` | Key in a custom header |
| `oauth2-client` | Client credentials grant (auto-refreshing) |
| `null` | No auth (internal network trust) |
