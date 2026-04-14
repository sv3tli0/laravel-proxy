# 2. Endpoint Groups & Routes

## Endpoint Groups

A named collection of proxy routes sharing common config: prefix, middleware, auth, rate limiting, logging.

Groups act as independent proxy namespaces. Each targets different upstreams, applies its own auth, and defines logging behavior.

**Group config keys:** `prefix`, `domain`, `middleware`, `auth`, `token_payload`, `rate_limit`, `cors`, `logging`, `pipeline`, `routes`

### Example (minimal)

```php
'groups' => [
    'app' => [
        'prefix' => '/api/v1',
        'auth'   => ['driver' => 'sanctum', 'guard' => 'api'],
        'token_payload' => UserTokenPayload::class,
        'routes' => [/* ... */],
    ],
],
```

## Route Definitions

Each route maps an external path to an internal upstream path.

**Route config keys:** `method`, `path`, `upstream`, `upstream_path`, `upstream_method`, `aggregate`, `response_transformer`, `cache`, `wildcard`, `inject_body`, `logging`

### Route Types

| Type | Description | Key field |
|------|-------------|-----------|
| Direct | 1:1 path forwarding | `upstream_path` matches `path` |
| Rewrite | External path differs from internal | `upstream_path` uses `{token.*}` variables |
| Aggregation | Fan-out to N services, merge | `aggregate` array instead of `upstream` |
| Wildcard | Catch-all forwarding | `wildcard: true` |
| Method override | POST externally, PATCH internally | `upstream_method` differs from `method` |

### Path Variable Resolution

Routes support variable substitution from two sources:

- **Route params:** `{id}`, `{slug}` — from the URL
- **TokenPayload:** `{token.user_id}`, `{token.tenant_id}` — from authenticated context

```
/me/orders → upstream: /api/users/{token.user_id}/orders
```
