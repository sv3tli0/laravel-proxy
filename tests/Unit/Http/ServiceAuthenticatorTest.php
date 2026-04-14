<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Http;

use Illuminate\Support\Facades\Http;
use Lararoxy\Http\ApiKeyAuthenticator;
use Lararoxy\Http\BearerAuthenticator;
use Lararoxy\Http\HmacAuthenticator;
use Lararoxy\Tests\TestCase;

class ServiceAuthenticatorTest extends TestCase
{
    public function test_bearer_authenticator_adds_authorization_header(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $auth = new BearerAuthenticator('test-token');
        $auth->authenticate(Http::baseUrl('http://test'))->get('/test');

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_api_key_authenticator_adds_custom_header(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $auth = new ApiKeyAuthenticator('my-key', 'X-Api-Key');
        $auth->authenticate(Http::baseUrl('http://test'))->get('/test');

        Http::assertSent(fn ($req) => $req->hasHeader('X-Api-Key', 'my-key'));
    }

    public function test_hmac_authenticator_adds_signature_and_timestamp(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $auth = new HmacAuthenticator('secret', 'sha256', 'X-Signature');
        $auth->authenticate(Http::baseUrl('http://test'))->post('/data', ['x' => 1]);

        Http::assertSent(function ($req) {
            return $req->hasHeader('X-Signature') && $req->hasHeader('X-Timestamp');
        });
    }

    public function test_hmac_signature_is_valid_hmac(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $key = 'test-secret';
        $auth = new HmacAuthenticator($key, 'sha256', 'X-Signature');
        $auth->authenticate(Http::baseUrl('http://test'))->get('/test');

        Http::assertSent(function ($req) use ($key) {
            $sig = $req->header('X-Signature')[0];
            $ts = $req->header('X-Timestamp')[0];
            $expected = hash_hmac('sha256', $ts.'.', $key);

            return $sig === $expected;
        });
    }
}
