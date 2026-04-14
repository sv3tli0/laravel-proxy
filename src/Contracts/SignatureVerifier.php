<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Illuminate\Http\Request;

interface SignatureVerifier
{
    /**
     * Verify the signature of an incoming callback request.
     */
    public function verify(Request $request, string $secret): bool;
}
