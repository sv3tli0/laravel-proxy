<?php

declare(strict_types=1);

namespace Lararoxy\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lararoxy\Contracts\LogDriverContract;
use Lararoxy\Data\ProxyContext;
use Lararoxy\Enums\LogLevel;

class RequestLogger
{
    public function __construct(
        protected LogDriverContract $driver,
        protected FieldRedactor $redactor,
        protected LogSampler $sampler,
        protected int $bodySizeLimit = 16384,
        protected array $excludePaths = [],
        protected array $escalation = [],
    ) {}

    /**
     * Ordinal ranking for log levels (higher = more verbose).
     */
    private const LEVEL_ORDER = [
        'none' => 0,
        'minimal' => 1,
        'standard' => 2,
        'full' => 3,
        'debug' => 4,
    ];

    /**
     * Determine the effective log level considering escalation rules.
     */
    public function resolveLevel(LogLevel $configuredLevel, ?int $statusCode = null): LogLevel
    {
        if ($statusCode === null) {
            return $configuredLevel;
        }

        if ($statusCode >= 500 && isset($this->escalation['5xx'])) {
            $escalated = LogLevel::tryFrom($this->escalation['5xx']);

            if ($escalated !== null && $this->levelOrd($escalated) > $this->levelOrd($configuredLevel)) {
                return $escalated;
            }
        }

        if ($statusCode >= 400 && $statusCode < 500 && isset($this->escalation['4xx'])) {
            $escalated = LogLevel::tryFrom($this->escalation['4xx']);

            if ($escalated !== null && $this->levelOrd($escalated) > $this->levelOrd($configuredLevel)) {
                return $escalated;
            }
        }

        return $configuredLevel;
    }

    private function levelOrd(LogLevel $level): int
    {
        return self::LEVEL_ORDER[$level->value] ?? 0;
    }

    /**
     * Determine if a request path is excluded from logging.
     */
    public function isExcluded(string $path): bool
    {
        foreach ($this->excludePaths as $excluded) {
            if (Str::is($excluded, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log a proxy request/response pair.
     */
    public function logRequest(ProxyContext $context, LogLevel $level): void
    {
        if ($level === LogLevel::None) {
            return;
        }

        if ($this->isExcluded($context->request->path())) {
            return;
        }

        if (! $this->sampler->shouldLog()) {
            return;
        }

        $record = $this->buildRecord($context, $level);
        $this->driver->log($record);
    }

    protected function buildRecord(ProxyContext $context, LogLevel $level): array
    {
        $request = $context->request;
        $requestId = $context->requestId ?? Str::ulid()->toBase32();

        $record = [
            'request_id' => $requestId,
            'group' => $context->group->name,
            'level' => $level->value,
            'method' => $request->method(),
            'path' => $request->path(),
            'created_at' => now(),
        ];

        // Minimal: add status + duration
        if ($context->upstreamResponse !== null) {
            $record['status_code'] = $context->upstreamResponse->status();
        }

        // Standard: add headers
        if ($level === LogLevel::Standard || $level === LogLevel::Full || $level === LogLevel::Debug) {
            $record['ip'] = $request->ip();
            $record['request_headers'] = $this->redactor->redactHeaders(
                $this->flattenHeaders($request)
            );
            $record['upstream_service'] = $context->route->upstream;
            $record['upstream_url'] = $context->resolvedUpstreamPath;

            if ($context->tokenPayload !== null) {
                $record['token_payload'] = method_exists($context->tokenPayload, 'upstreamHeaders')
                    ? $context->tokenPayload->upstreamHeaders()
                    : [];
            }
        }

        // Full: add bodies
        if ($level === LogLevel::Full || $level === LogLevel::Debug) {
            $record['request_body'] = $this->truncateBody($request->getContent());

            if ($context->upstreamResponse !== null) {
                $record['response_body'] = $this->truncateBody($context->upstreamResponse->body());
                $record['response_headers'] = $this->redactor->redactHeaders(
                    $context->upstreamResponse->headers() ?? []
                );
            }
        }

        // Debug: add pipeline trace
        if ($level === LogLevel::Debug) {
            $record['pipeline_trace'] = $context->pipelineTrace;
        }

        return $record;
    }

    protected function flattenHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }

    protected function truncateBody(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        if (strlen($body) > $this->bodySizeLimit) {
            return substr($body, 0, $this->bodySizeLimit).'...[truncated]';
        }

        return $body;
    }
}
