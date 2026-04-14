# 1. Architecture & Patterns

## Why Lararoxy

Modern apps need to:

- Expose a unified API surface routing to multiple internal services
- Protect internal APIs behind auth, rate limiting, and transformation
- Tailor responses per client type (mobile, web, admin, IoT)
- Make tracked outgoing calls and handle async callbacks
- Maintain audit trails without drowning in data

## Supported Patterns

| Pattern | Description | Lararoxy Feature |
|---------|-------------|-----------------|
| **API Gateway** | Single entry point to multiple backends | Endpoint Groups + Upstream Services |
| **Backend for Frontend (BFF)** | Client-specific API layers | Per-group routes with response transformers |
| **Reverse Proxy** | Transparent request forwarding | Direct proxy routes with path mapping |
| **API Aggregation** | Combine multiple service responses | Aggregation routes with fan-out and merge |
| **Anti-Corruption Layer** | Translate between API contracts | Request/response transformers per route |
| **Ambassador Pattern** | Retries, circuit breaking, monitoring | Retry policies, health checks, circuit breaker |
| **Strangler Fig** | Gradual traffic migration | Weighted upstream routing with fallback |
| **Edge Service** | Cross-cutting concerns at the boundary | Middleware stacks per group |
| **Service Facade** | Simplify complex internal API surface | Aggregation + transformation |
| **API Versioning Gateway** | Version negotiation | Version-aware route groups |
