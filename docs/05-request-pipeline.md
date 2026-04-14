# 5. Request Pipeline

Every proxied request flows through an ordered pipeline of stages.

## Pipeline Stages

```
Incoming Request
       |
  [1]  Route Matching         — Match to endpoint group + route definition
  [2]  Rate Limiting          — Apply group/route rate limits
  [3]  Authentication         — Validate token (Sanctum, API key, signature, etc.)
  [4]  TokenPayload Build     — Extract structured data from auth context
  [5]  Authorization          — TokenPayload.authorize() check
  [6]  Request Transform      — Rewrite path, inject headers, transform body
  [7]  Request Logging (pre)  — Log incoming request (per logging config)
  [8]  Upstream Dispatch      — Forward to upstream (with retry / circuit breaker)
  [9]  Response Transform     — Transform upstream response for the client
  [10] Request Logging (post) — Log response (per logging config)
  [11] Cache                  — Store response if caching configured
       |
Response to Client
```

## Custom Pipeline Stages

Custom stages can be added per group or per route:

```php
'pipeline' => [
    \App\Proxy\Stages\TenantIsolation::class,    // after auth, before dispatch
    \App\Proxy\Stages\ResponseEnvelope::class,    // after response transform
],
```

Each stage implements `ProxyPipelineStage` — receives request context, returns modified context or short-circuits with a response.

## Stage Ordering

Custom stages are inserted at configurable positions:

- `before:auth` — before authentication
- `after:auth` — after auth, before dispatch (most common)
- `after:response` — after upstream response, before client response
