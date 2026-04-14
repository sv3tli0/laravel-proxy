<?php

declare(strict_types=1);

namespace Lararoxy\Pipeline;

use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Lararoxy\Contracts\ProxyPipelineStage;
use Lararoxy\Data\ProxyContext;

class ProxyPipeline
{
    /** @var array<class-string<ProxyPipelineStage>> */
    protected array $defaultStages = [
        Stages\RateLimitingStage::class,
        Stages\AuthenticationStage::class,
        Stages\TokenPayloadBuildStage::class,
        Stages\AuthorizationStage::class,
        Stages\RequestTransformStage::class,
        Stages\PreLoggingStage::class,
        Stages\UpstreamDispatchStage::class,
        Stages\ResponseTransformStage::class,
        Stages\PostLoggingStage::class,
    ];

    public function __construct(
        protected Pipeline $pipeline,
    ) {}

    /**
     * Process a proxy context through the pipeline.
     */
    public function process(ProxyContext $context, array $customStages = []): Response
    {
        $stages = $this->resolveStages($customStages);

        return $this->pipeline
            ->send($context)
            ->through($stages)
            ->then(fn (ProxyContext $ctx) => $this->buildResponse($ctx));
    }

    /**
     * Merge custom stages into the default pipeline at configured positions.
     */
    protected function resolveStages(array $customStages): array
    {
        if (empty($customStages)) {
            return $this->defaultStages;
        }

        // Custom stages are inserted as class names — they're resolved from the container
        return array_merge($this->defaultStages, $customStages);
    }

    protected function buildResponse(ProxyContext $context): Response
    {
        $upstream = $context->upstreamResponse;

        if ($upstream === null) {
            return new Response('Bad Gateway', 502);
        }

        return new Response(
            $upstream->body(),
            $upstream->status(),
            $upstream->headers(),
        );
    }
}
