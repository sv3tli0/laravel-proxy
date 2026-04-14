# 4. Auth & TokenPayload

## Authentication Flow

```
Request → Auth Driver validates token → AuthModel resolved → TokenPayload built → Authorization check
```

## Auth Schemas

| Schema | Driver | Direction | Description |
|--------|--------|-----------|-------------|
| **Sanctum Token** | `sanctum` | Incoming | Personal access tokens with abilities |
| **Sanctum SPA** | `sanctum-spa` | Incoming | Cookie-based SPA auth |
| **API Key** | `api-key` | Both | Key in header or query param |
| **JWT** | `jwt` | Incoming | Decode/validate JWT claims |
| **HMAC Signature** | `signature` | Both | Sign/verify request body + timestamp |
| **OAuth2 Client** | `oauth2-client` | Outgoing | Client credentials grant |
| **Bearer Token** | `bearer` | Outgoing | Static/refreshable bearer |
| **Composite** | `composite` | Incoming | Chain multiple strategies |
| **Passthrough** | `passthrough` | Incoming | Forward auth headers as-is |
| **Custom** | `custom` | Both | Implement `AuthDriver` interface |

## TokenPayload

The bridge between authentication and gateway-level authorization. Built after auth validation, used for:

- **Route variable resolution:** `{token.user_id}` in upstream paths
- **Header injection:** Automatically inject identity headers to upstream
- **Authorization:** Gateway-level checks before request reaches upstream
- **Audit context:** Attach identity to logs

### Contract

TokenPayload implements three responsibilities:

1. **`fromAuth(AuthModel, PersonalAccessToken)`** — Build payload from auth context
2. **`authorize(routeName, routeParams)`** — Gate the request at the proxy level
3. **`upstreamHeaders()`** — Headers to inject into the forwarded request

### Common Payload Patterns

| Pattern | Fields | Use case |
|---------|--------|----------|
| **User Context** | user_id, email, roles, abilities | Standard user-facing API |
| **Multi-Tenant** | user_id, tenant_id, plan, features, rate_limit | SaaS / multi-tenant |
| **Service-to-Service** | service_name, version, allowed_endpoints, correlation_id | Internal microservices |
| **Scoped Permission** | user_id, scopes, expires_at, ip_whitelist | Fine-grained access control |

## AuthModel

Any Eloquent model implementing Sanctum's `HasApiTokens` + Lararoxy's `AuthModel` interface:

```php
class User extends Authenticatable implements LararoxyAuthModel
{
    use HasApiTokens;

    public function tokenPayloadClass(): string
    {
        return UserTokenPayload::class;
    }
}
```

Full Sanctum power (abilities, expiration, revocation) + structured payload for gateway decisions.
