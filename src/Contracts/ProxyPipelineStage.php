<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Closure;
use Lararoxy\Data\ProxyContext;

interface ProxyPipelineStage
{
    /**
     * Handle the proxy context through this pipeline stage.
     */
    public function handle(ProxyContext $context, Closure $next): mixed;
}
