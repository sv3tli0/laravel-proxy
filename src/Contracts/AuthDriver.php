<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Illuminate\Http\Request;

interface AuthDriver
{
    /**
     * Authenticate the incoming request.
     *
     * Return the authenticated model or null on failure.
     */
    public function authenticate(Request $request): ?AuthModel;
}
