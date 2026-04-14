<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Illuminate\Http\Request;

interface ResponseTransformer
{
    /**
     * Transform the upstream response before returning to the client.
     */
    public function transform(mixed $response, Request $originalRequest): mixed;
}
