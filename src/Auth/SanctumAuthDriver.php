<?php

declare(strict_types=1);

namespace Lararoxy\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lararoxy\Contracts\AuthDriver;
use Lararoxy\Contracts\AuthModel;

class SanctumAuthDriver implements AuthDriver
{
    public function __construct(
        protected string $guard = 'sanctum',
    ) {}

    public function authenticate(Request $request): ?AuthModel
    {
        $user = Auth::guard($this->guard)->user();

        if ($user instanceof AuthModel) {
            return $user;
        }

        return null;
    }
}
