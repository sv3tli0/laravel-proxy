# 10. Testing

Built with [Orchestra Testbench](https://github.com/orchestral/testbench) for testing within a Laravel environment.

## Package Tests

```bash
composer test
```

## Test Helpers for Consumer Apps

Lararoxy provides fakes and assertions for application tests:

### Faking Upstream Responses

```php
Lararoxy::fake();  // fake all

Lararoxy::fake([
    'users-service'  => Lararoxy::response(['id' => 1, 'name' => 'John'], 200),
    'orders-service' => Lararoxy::response(status: 500),
]);
```

### Asserting Proxy Calls

```php
Lararoxy::assertSent('users-service', fn ($req) => $req->url() === '/api/users/1');
Lararoxy::assertNotSent('notifications-service');
Lararoxy::assertSentCount('orders-service', 3);
```

### Asserting Outgoing Calls

```php
Lararoxy::assertOutgoing('payment-gateway', fn ($req) => $req->body()['amount'] === 5000);
```

### Asserting Tracking

```php
Lararoxy::assertTracked('trk_abc123', status: 'PROCESSED');
```
