<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\ProxyContext;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Enums\LogLevel;
use Lararoxy\Logging\DatabaseLogDriver;
use Lararoxy\Logging\FieldRedactor;
use Lararoxy\Logging\FileLogDriver;
use Lararoxy\Logging\LogSampler;
use Lararoxy\Logging\RequestLogger;
use Lararoxy\Models\ProxyLog;
use Lararoxy\Tests\TestCase;
use Lararoxy\Tests\Unit\Pipeline\StubTokenPayload;

class LoggingTest extends TestCase
{
    // ── FieldRedactor ──

    public function test_redacts_headers_case_insensitively(): void
    {
        $redactor = new FieldRedactor(headerNames: ['Authorization', 'Cookie'], fieldNames: []);

        $headers = $redactor->redactHeaders([
            'Authorization' => 'Bearer secret',
            'Content-Type' => 'application/json',
            'cookie' => 'session=abc',
        ]);

        $this->assertSame('***REDACTED***', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('***REDACTED***', $headers['cookie']);
    }

    public function test_redacts_nested_body_fields(): void
    {
        $redactor = new FieldRedactor(headerNames: [], fieldNames: ['password', 'cvv']);

        $body = $redactor->redactBody([
            'email' => 'user@example.com',
            'password' => 'secret123',
            'card' => ['number' => '4111', 'cvv' => '123'],
        ]);

        $this->assertSame('user@example.com', $body['email']);
        $this->assertSame('***REDACTED***', $body['password']);
        $this->assertSame('***REDACTED***', $body['card']['cvv']);
        $this->assertSame('4111', $body['card']['number']);
    }

    // ── LogSampler ──

    public function test_sampler_always_logs_when_disabled(): void
    {
        $sampler = new LogSampler(enabled: false);

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($sampler->shouldLog());
        }
    }

    public function test_sampler_never_logs_at_zero_rate(): void
    {
        $sampler = new LogSampler(enabled: true, rate: 0.0);

        for ($i = 0; $i < 10; $i++) {
            $this->assertFalse($sampler->shouldLog());
        }
    }

    public function test_sampler_always_logs_at_full_rate(): void
    {
        $sampler = new LogSampler(enabled: true, rate: 1.0);

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($sampler->shouldLog());
        }
    }

    public function test_sampler_partial_rate_produces_mixed_results(): void
    {
        $sampler = new LogSampler(enabled: true, rate: 0.5);
        $results = array_map(fn () => $sampler->shouldLog(), range(1, 100));

        $this->assertContains(true, $results);
        $this->assertContains(false, $results);
    }

    // ── FileLogDriver ──

    public function test_file_driver_query_returns_empty(): void
    {
        $this->assertSame([], (new FileLogDriver)->query());
    }

    public function test_file_driver_cleanup_returns_zero(): void
    {
        $this->assertSame(0, (new FileLogDriver)->cleanup(30));
    }

    // ── DatabaseLogDriver ──

    public function test_database_driver_stores_and_queries(): void
    {
        $driver = new DatabaseLogDriver;
        $driver->log([
            'request_id' => 'req_1', 'group' => 'api', 'level' => 'minimal',
            'method' => 'GET', 'path' => '/a', 'status_code' => 200, 'created_at' => now(),
        ]);
        $driver->log([
            'request_id' => 'req_2', 'tracking_id' => 'trk_x', 'group' => 'admin', 'level' => 'minimal',
            'method' => 'POST', 'path' => '/b', 'status_code' => 500, 'created_at' => now(),
        ]);

        $this->assertCount(1, $driver->query(['group' => 'api']));
        $this->assertCount(1, $driver->query(['status_code' => 500]));
        $this->assertCount(1, $driver->query(['request_id' => 'req_1']));
        $this->assertCount(1, $driver->query(['tracking_id' => 'trk_x']));
        $this->assertCount(2, $driver->query(['since' => now()->subHour()]));
        $this->assertCount(0, $driver->query(['until' => now()->subHour()]));
    }

    public function test_database_driver_cleanup_with_max_records(): void
    {
        for ($i = 0; $i < 5; $i++) {
            ProxyLog::create([
                'request_id' => "req_$i", 'group' => 'api', 'level' => 'minimal',
                'method' => 'GET', 'path' => '/a', 'created_at' => now(),
            ]);
        }

        $deleted = (new DatabaseLogDriver)->cleanup(0, 2);

        $this->assertGreaterThanOrEqual(3, $deleted);
        $this->assertLessThanOrEqual(2, ProxyLog::count());
    }

    // ── RequestLogger ──

    public function test_skips_none_level(): void
    {
        $logger = $this->makeLogger();
        $logger->logRequest($this->makeContext('req_none'), LogLevel::None);

        $this->assertDatabaseMissing('proxy_logs', ['request_id' => 'req_none']);
    }

    public function test_skips_excluded_paths(): void
    {
        $logger = $this->makeLogger(excludePaths: ['health']);
        $context = $this->makeContext('req_excl', path: '/health');

        $logger->logRequest($context, LogLevel::Full);

        $this->assertDatabaseMissing('proxy_logs', ['request_id' => 'req_excl']);
    }

    public function test_minimal_level_stores_basic_fields(): void
    {
        $logger = $this->makeLogger();
        $logger->logRequest($this->makeContext('req_min'), LogLevel::Minimal);

        $this->assertDatabaseHas('proxy_logs', [
            'request_id' => 'req_min',
            'group' => 'api',
            'method' => 'POST',
        ]);
    }

    public function test_standard_level_includes_headers_and_redacts(): void
    {
        $logger = $this->makeLogger();
        $context = $this->makeContext('req_std', headers: ['Authorization' => 'Bearer secret']);

        $logger->logRequest($context, LogLevel::Standard);

        $log = ProxyLog::where('request_id', 'req_std')->first();
        $this->assertNotNull($log->request_headers);
        $this->assertSame('***REDACTED***', $log->request_headers['authorization']);
    }

    public function test_full_level_includes_body_and_truncates(): void
    {
        $longBody = str_repeat('x', 200);
        $logger = $this->makeLogger(bodySizeLimit: 50);
        $context = $this->makeContext('req_full', body: $longBody);

        $logger->logRequest($context, LogLevel::Full);

        $log = ProxyLog::where('request_id', 'req_full')->first();
        $this->assertStringContainsString('[truncated]', $log->request_body);
    }

    public function test_full_level_with_upstream_response(): void
    {
        Http::fake(['*' => Http::response(['data' => 'ok'], 200)]);
        $response = Http::get('http://test/fake');

        $logger = $this->makeLogger();
        $context = $this->makeContext('req_resp');
        $context->upstreamResponse = $response;

        $logger->logRequest($context, LogLevel::Full);

        $log = ProxyLog::where('request_id', 'req_resp')->first();
        $this->assertNotNull($log->response_body);
        $this->assertSame(200, $log->status_code);
    }

    public function test_debug_level_includes_pipeline_trace(): void
    {
        $logger = $this->makeLogger();
        $context = $this->makeContext('req_dbg');
        $context->addTrace('test_stage', 1.5);

        $logger->logRequest($context, LogLevel::Debug);

        $log = ProxyLog::where('request_id', 'req_dbg')->first();
        $this->assertNotNull($log->pipeline_trace);
        $this->assertSame('test_stage', $log->pipeline_trace[0]['stage']);
    }

    public function test_escalation_upgrades_level_for_5xx(): void
    {
        $logger = $this->makeLogger(escalation: ['5xx' => 'full', '4xx' => 'standard']);

        $this->assertSame(LogLevel::Full, $logger->resolveLevel(LogLevel::Minimal, 503));
        $this->assertSame(LogLevel::Standard, $logger->resolveLevel(LogLevel::Minimal, 404));
        $this->assertSame(LogLevel::Minimal, $logger->resolveLevel(LogLevel::Minimal, 200));
        $this->assertSame(LogLevel::Minimal, $logger->resolveLevel(LogLevel::Minimal, null));
    }

    public function test_is_excluded_uses_glob_matching(): void
    {
        $logger = $this->makeLogger(excludePaths: ['/health', '/ping', '/metrics*']);

        $this->assertTrue($logger->isExcluded('/health'));
        $this->assertTrue($logger->isExcluded('/ping'));
        $this->assertFalse($logger->isExcluded('/api/users'));
    }

    public function test_standard_level_includes_token_payload(): void
    {
        $logger = $this->makeLogger();
        $context = $this->makeContext('req_tok');
        $context->tokenPayload = new StubTokenPayload(77, 'u@t.com');

        $logger->logRequest($context, LogLevel::Standard);

        $log = ProxyLog::where('request_id', 'req_tok')->first();
        $this->assertSame('77', $log->token_payload['X-User-Id']);
    }

    private function makeLogger(
        int $bodySizeLimit = 16384,
        array $excludePaths = [],
        array $escalation = [],
    ): RequestLogger {
        return new RequestLogger(
            driver: new DatabaseLogDriver,
            redactor: new FieldRedactor(['Authorization'], ['password']),
            sampler: new LogSampler(enabled: false),
            bodySizeLimit: $bodySizeLimit,
            excludePaths: $excludePaths,
            escalation: $escalation,
        );
    }

    private function makeContext(
        string $requestId,
        string $path = '/api/test',
        ?string $body = null,
        array $headers = [],
    ): ProxyContext {
        $serverVars = [];
        foreach ($headers as $key => $val) {
            $serverVars['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $val;
        }

        return new ProxyContext(
            request: Request::create($path, 'POST', [], [], [], $serverVars, $body ?? ''),
            group: new GroupDefinition(name: 'api'),
            route: new RouteDefinition(method: 'POST', path: $path, upstream: 'svc', upstreamPath: $path),
            requestId: $requestId,
        );
    }
}
