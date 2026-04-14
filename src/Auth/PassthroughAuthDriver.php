<?php

declare(strict_types=1);

namespace Lararoxy\Auth;

use Illuminate\Http\Request;
use Lararoxy\Contracts\AuthDriver;
use Lararoxy\Contracts\AuthModel;

/**
 * Passes through auth headers to upstream without validation.
 * Returns null (no AuthModel) — used when upstream handles its own auth.
 */
class PassthroughAuthDriver implements AuthDriver
{
    public function authenticate(Request $request): ?AuthModel
    {
        // No local validation — headers are forwarded as-is
        return null;
    }
}
