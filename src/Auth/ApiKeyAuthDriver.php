<?php

declare(strict_types=1);

namespace Lararoxy\Auth;

use Illuminate\Http\Request;
use Lararoxy\Contracts\AuthDriver;
use Lararoxy\Contracts\AuthModel;

class ApiKeyAuthDriver implements AuthDriver
{
    public function __construct(
        protected string $header = 'X-Api-Key',
        protected ?\Closure $resolver = null,
    ) {}

    public function authenticate(Request $request): ?AuthModel
    {
        $apiKey = $request->header($this->header);

        if ($apiKey === null) {
            return null;
        }

        if ($this->resolver !== null) {
            $model = ($this->resolver)($apiKey);

            return $model instanceof AuthModel ? $model : null;
        }

        return null;
    }
}
