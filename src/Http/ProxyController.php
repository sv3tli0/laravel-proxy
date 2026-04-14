<?php

declare(strict_types=1);

namespace Lararoxy\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\ProxyContext;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Pipeline\ProxyPipeline;

class ProxyController extends Controller
{
    public function __construct(
        protected ProxyPipeline $pipeline,
    ) {}

    /**
     * Handle an incoming proxy request.
     */
    public function handle(
        Request $request,
        GroupDefinition $group,
        RouteDefinition $route,
    ): Response {
        $context = new ProxyContext(
            request: $request,
            group: $group,
            route: $route,
        );

        return $this->pipeline->process($context, $group->pipeline);
    }
}
