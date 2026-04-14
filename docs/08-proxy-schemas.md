# 8. Proxy Schemas

All supported flow patterns organized by direction.

## Incoming Proxy Schemas

### 1. Direct Proxy
1:1 path forwarding.
```
Client → /api/v1/users/42 → [Lararoxy] → http://users-svc/api/users/42
```

### 2. Path Rewrite
External and internal paths differ. Variables resolved from route params and TokenPayload.
```
Client → /me/orders → [Lararoxy] → http://orders-svc/api/users/{token.user_id}/orders
```

### 3. Auth Gateway
Validate token → build payload → inject identity headers → forward. Upstream trusts injected headers.
```
Client (Bearer) → [Validate] → [Payload] → [Inject X-User-Id] → Upstream
```

### 4. BFF (Backend for Frontend)
Client-specific endpoints reshaping data. Uses response transformers, may aggregate upstreams.
```
Mobile → /mobile/api/home → [Lararoxy] → users-svc + posts-svc + ads-svc → MobileTransformer → response
```

### 5. Aggregation
Fan-out to N services in parallel, merge results.
```
Client → /dashboard → [Lararoxy] → analytics (/stats) + users (/me) + notifications (/unread) → merged
```

### 6. Cached Proxy
Cache upstream response at the gateway with configurable TTL.
```
Client → /config → [Cache HIT?] → cached response | MISS → upstream → cache → response
```

### 7. Versioned Proxy
Route to different upstreams based on API version (path prefix, header, or query).
```
/api/v2/users/42 → [v2 group] → http://users-v2/users/42
/api/v1/users/42 → [v1 group] → http://legacy-users/api/users/42
```

### 8. Strangler Fig
Weighted traffic split between old and new service. Configurable fallback.
```
80% → new-orders-service
20% → legacy-monolith (also fallback on failure)
```

### 9. Method Translation
Incoming method differs from upstream method.
```
POST /users/42/activate → [Lararoxy] → PATCH /users/42 {"active": true}
```

### 10. Passthrough
Wildcard catch-all forwarding everything unmatched to a fallback upstream.

---

## Outgoing Call Schemas

### 1. Tracked Synchronous
Send, get response immediately. Tracking ID stored for callback matching.

### 2. Tracked Queued
Dispatch via Laravel queue. Tracking ID available before the job runs.

### 3. Signed Request
Body/headers signed (HMAC) automatically per service config.

### 4. Fire-and-Forget
Send untracked. For non-critical calls.

### 5. Retry-able
Retries on failure with exponential backoff per retry policy.

### 6. Batch
Multiple calls as a batch. Each gets its own tracking ID.

---

## Callback Schemas

### 1. Tracking ID Match
Match incoming callback to outgoing request via tracking ID (in URL, header, or body).

### 2. Signature-Verified
Verify callback signature before processing. Rejection logged/alerted.

### 3. State Machine
Each callback transitions the tracked request:
```
PENDING → SENT → CALLBACK_RECEIVED → PROCESSED
                                    → FAILED
              → FAILED_TO_SEND
              → EXPIRED (TTL)
```

### 4. Event-Driven
Laravel events fired on callback receipt:
- `CallbackReceived` — any callback
- `TrackingCompleted` — terminal state reached
- `CallbackVerificationFailed` — signature failure
