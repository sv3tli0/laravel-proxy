# 11. Laravel 13 PHP Attributes Reference

> Verified against `laravel/framework` v13.4.0 vendor source. PHP 8.3+ required.

Laravel 13 ships **71 attribute classes** across 8 namespaces. All are optional — the old property/method approach still works. Attributes are the modern, type-safe alternative.

## Key Patterns from Laravel's Implementation

- Attributes use **constructor property promotion** with `public` (not `readonly` in most cases)
- Laravel does **NOT** use `declare(strict_types=1)` in its attribute classes
- Simple attributes are often `final class` (e.g., `Singleton`), complex ones are just `class`
- Nullable properties default to `null` for optional config
- Laravel prefers separate small attributes over one mega-attribute (e.g., `#[Tries]`, `#[Timeout]`, `#[Backoff]` instead of one `#[QueueConfig]`)

---

## Eloquent Model Attributes (23)

**Namespace:** `Illuminate\Database\Eloquent\Attributes`

| Attribute | Target | Replaces | Purpose |
|-----------|--------|----------|---------|
| `#[Table]` | CLASS | `$table`, `$primaryKey`, `$keyType`, `$incrementing`, `$timestamps`, `$dateFormat` | Consolidates table config |
| `#[Fillable]` | CLASS | `$fillable` | Mass-assignable fields |
| `#[Guarded]` | CLASS | `$guarded` | Protected fields |
| `#[Unguarded]` | CLASS | `$guarded = []` | Disables mass-assignment protection |
| `#[Hidden]` | CLASS | `$hidden` | Excludes from JSON |
| `#[Visible]` | CLASS | `$visible` | Includes in JSON |
| `#[Appends]` | CLASS | `$appends` | Auto-appends accessors to JSON |
| `#[Connection]` | CLASS | `$connection` | Database connection |
| `#[DateFormat]` | CLASS | `$dateFormat` | Date serialization format |
| `#[Touches]` | CLASS | `$touches` | Updates parent timestamps |
| `#[WithoutTimestamps]` | CLASS | `$timestamps = false` | Disables timestamps |
| `#[WithoutIncrementing]` | CLASS | `$incrementing = false` | Disables auto-increment |
| `#[ObservedBy]` | CLASS | manual registration | Registers observer |
| `#[ScopedBy]` | CLASS | `addGlobalScope()` | Applies global scope class |
| `#[Scope]` | CLASS | N/A | Defines global scope |
| `#[CollectedBy]` | CLASS | `$collectionClass` | Custom collection class |
| `#[UseEloquentBuilder]` | CLASS | `newEloquentBuilder()` | Custom builder class |
| `#[UseFactory]` | CLASS | `newFactory()` | Associates factory |
| `#[UsePolicy]` | CLASS | Policy auto-discovery | Associates policy |
| `#[UseResource]` | CLASS | N/A | Associates API resource |
| `#[UseResourceCollection]` | CLASS | N/A | Associates resource collection |
| `#[Boot]` | METHOD | `boot()` | Model boot hook |
| `#[Initialize]` | METHOD | `initializeTraitName()` | Trait init hook |

## Queue/Job Attributes (11)

**Namespace:** `Illuminate\Queue\Attributes`

Also work on Listeners, Notifications, and Mailables that implement `ShouldQueue`.

| Attribute | Replaces | Purpose |
|-----------|----------|---------|
| `#[Tries]` | `$tries` | Max retry attempts |
| `#[Timeout]` | `$timeout` | Max execution seconds |
| `#[Backoff]` | `$backoff` | Retry delay strategy |
| `#[Connection]` | `$connection` | Queue connection |
| `#[Queue]` | `$queue` | Queue name |
| `#[Delay]` | `$delay` | Processing delay |
| `#[MaxExceptions]` | `$maxExceptions` | Exception limit |
| `#[FailOnTimeout]` | `$failOnTimeout` | Fail vs retry on timeout |
| `#[DeleteWhenMissingModels]` | `$deleteWhenMissingModels` | Auto-delete on missing model |
| `#[UniqueFor]` | `$uniqueFor` | Unique lock duration |
| `#[WithoutRelations]` | `$withoutRelations` | Strips relations before serializing |

## Container Attributes (16)

**Namespace:** `Illuminate\Container\Attributes`

| Attribute | Purpose |
|-----------|---------|
| `#[Singleton]` | Marks class as singleton in container |
| `#[Scoped]` | Scoped to request/job lifecycle |
| `#[Bind]` | Specifies implementation for interface |
| `#[Give]` | Contextual binding value |
| `#[Tag]` | Injects tagged services |
| `#[Config]` | Injects config value |
| `#[Auth]` | Injects auth guard |
| `#[Authenticated]` | Injects authenticated user |
| `#[CurrentUser]` | Injects current user |
| `#[Cache]` | Injects cache store |
| `#[DB]` / `#[Database]` | Injects database connection |
| `#[Log]` | Injects logger channel |
| `#[Storage]` | Injects filesystem disk |
| `#[RouteParameter]` | Injects route parameter |
| `#[Context]` | Injects contextual data |

## Console Command Attributes (6)

**Namespace:** `Illuminate\Console\Attributes`

| Attribute | Replaces | Purpose |
|-----------|----------|---------|
| `#[Signature]` | `$signature` | Command name + args + options |
| `#[Description]` | `$description` | Command description |
| `#[Aliases]` | `$aliases` | Command aliases |
| `#[Help]` | `$help` | Extended help text |
| `#[Hidden]` | `$hidden` | Hide from artisan list |
| `#[Usage]` | N/A | Usage examples (repeatable) |

## Routing/Controller Attributes (2)

**Namespace:** `Illuminate\Routing\Attributes\Controllers`

| Attribute | Replaces | Purpose |
|-----------|----------|---------|
| `#[Middleware]` | `$this->middleware()` | Attach middleware (CLASS+METHOD, repeatable) |
| `#[Authorize]` | `$this->authorizeResource()` | Gate/policy authorization |

## Form Request Attributes (5)

**Namespace:** `Illuminate\Foundation\Http\Attributes`

| Attribute | Replaces | Purpose |
|-----------|----------|---------|
| `#[ErrorBag]` | `$errorBag` | Named error bag |
| `#[FailOnUnknownFields]` | N/A | Reject unknown fields |
| `#[RedirectTo]` | `$redirect` | Redirect URL on failure |
| `#[RedirectToRoute]` | `$redirectRoute` | Redirect route on failure |
| `#[StopOnFirstFailure]` | `$stopOnFirstFailure` | Halt after first error |

## HTTP Resource Attributes (2)

**Namespace:** `Illuminate\Http\Resources\Attributes`

| Attribute | Replaces | Purpose |
|-----------|----------|---------|
| `#[Collects]` | `$collects` | Resource type for collections |
| `#[PreserveKeys]` | `$preserveKeys` | Maintain array keys |

## Testing Attributes (5)

**Namespace:** `Illuminate\Foundation\Testing\Attributes`

| Attribute | Replaces | Purpose |
|-----------|----------|---------|
| `#[Seed]` | `$seed` | Run default seeder |
| `#[Seeder]` | `$seeder` | Run specific seeder |
| `#[SetUp]` | `setUp()` | Test init hook |
| `#[TearDown]` | `tearDown()` | Test cleanup hook |
| `#[UnitTest]` | N/A | Skip framework boot (added 13.3) |

---

## What Laravel 13 Does NOT Have

- No route definition attributes (`#[Get]`, `#[Post]`, `#[Route]`) — use `spatie/laravel-route-attributes` for this
- No `#[Scheduled]` for command scheduling
- No `#[Validate]` for inline validation
- No `#[Casts]` on models — still uses `casts()` method

---

## Design Principles for Lararoxy Attributes

Based on Laravel 13's patterns:

1. **Small, focused attributes** — one concern per attribute (not mega-config attributes)
2. **Constructor property promotion** — `public` properties, nullable for optional values
3. **Repeatable where logical** — routes, sources, middleware
4. **CLASS for config, METHOD for behavior** — group-level config on class, route-level on methods
5. **Leverage Laravel's container attributes** — use `#[Singleton]`, `#[Scoped]`, `#[Config]` where applicable
6. **Follow Laravel's naming** — match the verb/noun style (`#[Middleware]`, `#[Tries]`, `#[Table]`)
