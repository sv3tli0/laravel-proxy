# 6. Outgoing Calls & Tracking

## Overview

Lararoxy provides tracked outgoing HTTP calls to external services. Every call gets a **Tracking ID** connecting the initial request to any future callbacks.

## Outgoing Service Config Keys

| Key | Type | Description |
|-----|------|-------------|
| `base_url` | string | External service URL |
| `timeout` | int | Request timeout |
| `auth` | array | Outgoing auth (bearer, hmac, api-key, oauth2-client) |
| `tracking` | array | `enabled`, `id_generator`, `store`, `ttl`, `id_header` |
| `callback` | array | `path`, `signature_header`, `signature_verifier`, `handler` |
| `retry` | array | `times`, `delay`, `multiplier` |
| `queue` | array | `enabled`, `connection`, `queue` |
| `logging` | array | `level` |

## Dispatching

```php
// Synchronous — get response immediately
$response = Lararoxy::outgoing('payment-gateway')->post('/charges', $data);
$response->trackingId();  // "trk_a1b2c3..."
$response->json();

// Queued — dispatched via Laravel queue
$tracking = Lararoxy::outgoing('email-service')->queued()->post('/send', $data);
$tracking->trackingId();  // available immediately

// Untracked — fire-and-forget
Lararoxy::outgoing('analytics')->untracked()->post('/events', $data);
```

## Tracking Stores

| Store | Best for |
|-------|----------|
| `database` | Durability, queryable history, audit trails |
| `redis` | Speed, short-lived tracking, high throughput |
| `cache` | Simple setups using Laravel's cache driver |

## Tracking Lifecycle

```
PENDING → SENT → CALLBACK_RECEIVED → PROCESSED
                                    → FAILED
              → FAILED_TO_SEND
              → EXPIRED (TTL exceeded)
```

## Callback Handling

When an external service sends a callback, Lararoxy:

1. Matches it to the original request via Tracking ID (URL path, header, or body)
2. Verifies signature (if configured)
3. Routes to the configured `CallbackHandler`
4. Updates tracking status to `PROCESSED`

Handler contract:

```php
class PaymentCallbackHandler implements CallbackHandler
{
    public function handle(Request $request, TrackedRequest $tracked): void { /* ... */ }
    public function onVerificationFailed(Request $request): void { /* ... */ }
}
```

## Events

| Event | Fired when |
|-------|-----------|
| `CallbackReceived` | Any callback arrives |
| `TrackingCompleted` | Tracked request reaches terminal state |
| `CallbackVerificationFailed` | Signature verification fails |
| `OutgoingRequestSent` | Outgoing call dispatched |
| `OutgoingRequestFailed` | Outgoing call failed after retries |
