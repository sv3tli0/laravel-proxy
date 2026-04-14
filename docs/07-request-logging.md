# 7. Request Logging

Logging is critical for observability but can consume storage rapidly. Lararoxy provides granular control over what gets logged, how much, and for how long.

## Logging Levels

| Level | What is logged | Use case |
|-------|---------------|----------|
| `none` | Nothing | Health checks, high-frequency internal routes |
| `minimal` | Method, path, status, duration, request ID | Production high-traffic |
| `standard` | Minimal + filtered headers | Production authenticated APIs |
| `full` | Standard + request/response bodies (size-capped) | Audit-sensitive routes |
| `debug` | Everything + internal pipeline trace | Development only |

## Controls

### Sampling
Log only a percentage of requests. Configurable globally, per-group, per-route.

```php
'sampling' => ['enabled' => true, 'rate' => 0.1]  // 10% of requests
```

### Body Size Limit
Truncate logged bodies beyond a threshold. Prevents logging massive payloads.

```php
'body_size_limit' => 16384  // 16 KB
```

### Retention
Auto-cleanup with time-based and count-based caps.

```php
'retention' => ['days' => 30, 'max_records' => 1_000_000, 'cleanup_schedule' => 'daily']
```

### Redaction
Strip sensitive headers and mask sensitive body fields.

```php
'redact_headers' => ['Authorization', 'Cookie', 'X-Api-Key']
'redact_fields'  => ['password', 'credit_card', 'cvv', 'ssn', 'token']
```

### Path Exclusion
Never log requests matching certain paths.

```php
'exclude_paths' => ['/health', '/ping', '/metrics']
```

### Status-Based Escalation
Automatically upgrade log level for errors.

```php
'escalation' => ['4xx' => 'standard', '5xx' => 'full']
```

### Slow Request Logging
Always log full details for slow requests, regardless of sampling.

```php
'log_slow_requests' => ['enabled' => true, 'threshold' => 2000]  // ms
```

## Override Hierarchy

Route config > Group config > Global config

A route with `logging.level: 'full'` overrides a group's `logging.level: 'minimal'`.

## Log Record Structure

```json
{
    "id": "log_...",
    "request_id": "req_...",
    "tracking_id": "trk_...",
    "group": "app",
    "timestamp": "2026-04-13T14:30:00Z",
    "duration_ms": 142,
    "request":  { "method": "POST", "path": "/api/v1/orders", "ip": "..." },
    "upstream": { "service": "orders-service", "url": "...", "duration_ms": 128 },
    "response": { "status": 201 },
    "token_payload": { "user_id": 42, "tenant_id": "..." },
    "tags": ["order"]
}
```

Body fields included only at `full`/`debug` levels. Sensitive fields replaced with `***REDACTED***`.

## Storage Drivers

| Driver | Description |
|--------|-------------|
| `database` | Queryable, supports retention cleanup via scheduled command |
| `file` | Append to log files, lightweight |
| `custom` | Implement `LogDriver` interface |
