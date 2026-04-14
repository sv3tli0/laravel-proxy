<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Illuminate\Http\Request;

interface RequestTransformer
{
    /**
     * Transform the request before dispatching to upstream.
     */
    public function transform(Request $request, array $routeConfig): Request;
}
