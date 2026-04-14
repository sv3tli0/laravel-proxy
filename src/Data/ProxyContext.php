<?php

declare(strict_types=1);

namespace Lararoxy\Data;

use Illuminate\Http\Request;

class ProxyContext
{
    public function __construct(
        public readonly Request $request,
        public readonly GroupDefinition $group,
        public readonly RouteDefinition $route,
        public ?object $authModel = null,
        public ?object $tokenPayload = null,
        public array $upstreamHeaders = [],
        public ?string $resolvedUpstreamPath = null,
        public ?string $resolvedUpstreamMethod = null,
        public mixed $upstreamResponse = null,
        public ?string $requestId = null,
        public array $pipelineTrace = [],
    ) {}

    public function addTrace(string $stage, float $durationMs): void
    {
        $this->pipelineTrace[] = [
            'stage' => $stage,
            'duration_ms' => round($durationMs, 2),
        ];
    }
}
