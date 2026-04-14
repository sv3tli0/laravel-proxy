# 9. Configuration

Three configuration approaches, merged at boot. Precedence: **Fluent API > Attributes > Config file**.

| Approach | Best for |
|----------|----------|
| **Config file** (`config/lararoxy.php`) | Quick setup, env-driven values, simple proxies |
| **Fluent API** (`routes/proxy.php`) | Laravel-style chainable declarations |
| **PHP Attributes** | Type-safe, IDE-friendly, co-located with handler code |

> PHP Attributes work identically on Laravel 12 and 13.

---

## Config File

Standard Laravel config array. See full reference at the bottom.

---

## Fluent API (`Proxy::` facade)

Registered in `routes/proxy.php` (auto-loaded). **Not** Laravel's `Route::` — fully independent proxy-specific facade.

### Services

```php
Proxy::service('users-service')
    ->baseUrl(env('USERS_SERVICE_URL'))
    ->timeout(30)
    ->retry(times: 3, delay: 100, multiplier: 2)
    ->circuitBreaker(threshold: 5, timeout: 30)
    ->bearerAuth(env('USERS_SERVICE_TOKEN'));
```

### Groups & Routes

```php
Proxy::group('app', function () {
    Proxy::get('/users/{id}', 'users-service', '/api/users/{id}');
    Proxy::post('/orders', 'orders-service', '/api/orders')->rateLimit(10, 1);
})->prefix('/api/v1')
  ->auth('sanctum', guard: 'api')
  ->tokenPayload(UserTokenPayload::class);
```

### Aggregation

```php
Proxy::group('mobile-bff', function () {
    Proxy::aggregate('/home')
        ->from('users-service', '/api/users/{token.user_id}/profile', as: 'profile')
        ->from('feed-service', '/api/feed?limit=20', as: 'feed')
        ->transformResponse(MobileHomeTransformer::class);
})->prefix('/mobile/api/v1')->auth('sanctum');
```

### Outgoing

```php
Proxy::outgoing('payment-gateway')
    ->baseUrl(env('PAYMENT_GATEWAY_URL'))
    ->hmacAuth(env('PAYMENT_HMAC_KEY'))
    ->tracking(store: 'database', ttl: 86400)
    ->callback(path: '/webhooks/payments/{tracking_id}', handler: PaymentCallbackHandler::class);
```

### Webhooks

```php
Proxy::webhooks(function () {
    Proxy::webhook('/payments/{tracking_id}')
        ->matchTracking()
        ->verifySignature('X-Signature', PaymentSignatureVerifier::class)
        ->handle(PaymentCallbackHandler::class);
})->prefix('/webhooks');
```

### Route File Loading

Auto-loads `routes/proxy.php`. Customizable:

```php
'routes' => ['files' => [base_path('routes/proxy.php'), base_path('routes/proxy-admin.php')]]
```

---

## PHP Attributes

### Endpoint Group

```php
#[EndpointGroup(name: 'app', prefix: '/api/v1', middleware: ['throttle:120,1'])]
#[Auth(driver: 'sanctum', guard: 'api')]
class AppProxyGroup
{
    #[ProxyRoute(method: 'GET', path: '/users/{id}', upstream: 'users-service', upstreamPath: '/api/users/{id}')]
    public function getUser() {}

    #[ProxyRoute(method: 'POST', path: '/orders', upstream: 'orders-service', upstreamPath: '/api/orders')]
    #[RateLimit(maxAttempts: 10, decayMinutes: 1)]
    public function createOrder() {}
}
```

### Upstream Service

```php
#[UpstreamService(name: 'users-service', baseUrl: 'USERS_SERVICE_URL', timeout: 30)]
#[Retry(times: 3, delay: 100, multiplier: 2, on: [500, 502, 503, 504])]
#[CircuitBreaker(threshold: 5, timeout: 30)]
class UsersServiceDefinition {}
```

### Aggregation

```php
#[AggregateRoute(method: 'GET', path: '/home', responseTransformer: MobileHomeTransformer::class)]
#[AggregateSource(upstream: 'users-service', path: '/api/users/{token.user_id}/profile', as: 'profile')]
#[AggregateSource(upstream: 'feed-service', path: '/api/feed?limit=20', as: 'feed')]
public function home() {}
```

### Outgoing Service

```php
#[OutgoingService(name: 'payment-gateway', baseUrl: 'PAYMENT_GATEWAY_URL', timeout: 30)]
#[Tracking(enabled: true, store: 'database', ttl: 86400)]
#[Callback(path: '/webhooks/payments/{tracking_id}', handler: PaymentCallbackHandler::class)]
class PaymentGatewayDefinition {}
```

### TokenPayload Fields

```php
class UserTokenPayload implements TokenPayload
{
    #[TokenField(from: 'model.id')]
    #[InjectHeader('X-User-Id')]
    public readonly int $user_id;

    #[TokenField(from: 'model.tenant_id')]
    #[InjectHeader('X-Tenant-Id')]
    public readonly string $tenant_id;

    #[TokenField(from: 'model.roles', resolver: RolesResolver::class)]
    #[InjectHeader('X-User-Roles', join: ',')]
    public readonly array $roles;
}
```

### Attribute Discovery

```php
// config/lararoxy.php
'attributes' => [
    'enabled' => true,
    'scan_paths' => [app_path('Proxy')],
    'cache' => env('LARAROXY_CACHE_ATTRIBUTES', true),
],
```

---

## Config Reference

Full `config/lararoxy.php` key structure:

```
services.<name>.base_url              string
services.<name>.timeout               int (seconds)
services.<name>.connect_timeout       int (seconds)
services.<name>.retry                 {times, delay, multiplier, on}
services.<name>.circuit_breaker       {enabled, threshold, timeout}
services.<name>.health_check          {path, interval}
services.<name>.auth                  {type, ...driver-specific}

groups.<name>.prefix                  string
groups.<name>.domain                  string (optional)
groups.<name>.middleware              array
groups.<name>.auth                    {driver, ...} | null
groups.<name>.token_payload           class string
groups.<name>.rate_limit              string | array
groups.<name>.cors                    {allowed_origins, allowed_methods, ...} | null
groups.<name>.logging                 {level, sampling, ...}
groups.<name>.pipeline                array (custom stages)
groups.<name>.routes                  array

outgoing.<name>.base_url              string
outgoing.<name>.timeout               int
outgoing.<name>.auth                  {type, ...}
outgoing.<name>.tracking              {enabled, id_generator, store, ttl, id_header}
outgoing.<name>.callback              {path, signature_header, signature_verifier, handler}
outgoing.<name>.retry                 {times, delay, multiplier}
outgoing.<name>.queue                 {enabled, connection, queue}
outgoing.<name>.logging               {level}

logging.default_level                 string
logging.driver                        string (database, file, custom)
logging.body_size_limit               int (bytes)
logging.sampling                      {enabled, rate}
logging.retention                     {enabled, days, max_records, cleanup_schedule}
logging.redact_headers                array
logging.redact_fields                 array
logging.exclude_paths                 array
logging.conditions                    {log_errors_only, log_slow_requests}
logging.escalation                    {4xx, 5xx}

tracking.default_store                string
tracking.id_generator                 class string
tracking.id_prefix                    string
tracking.retention_days               int

request_id.enabled                    bool
request_id.header                     string
request_id.trust_incoming             bool
request_id.generator                  class string
request_id.forward                    bool

http.default_timeout                  int
http.default_connect_timeout          int
http.verify_ssl                       bool
http.pool                             {enabled, max_connections}

attributes.enabled                    bool
attributes.scan_paths                 array
attributes.cache                      bool

routes.files                          array
```
