<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

use Laravel\Sanctum\Contracts\HasAbilities;

interface TokenPayload
{
    /**
     * Build the payload from the authenticated model and its access token.
     */
    public static function fromAuth(AuthModel $model, ?HasAbilities $accessToken = null): static;

    /**
     * Gateway-level authorization check.
     *
     * Return false to reject the request before it reaches the upstream.
     */
    public function authorize(string $routeName, array $routeParams): bool;

    /**
     * Headers to inject into the upstream request.
     *
     * @return array<string, string>
     */
    public function upstreamHeaders(): array;

    /**
     * Resolve a named value for path variable substitution.
     *
     * Supports {token.user_id}, {token.tenant_id}, etc.
     */
    public function resolve(string $key): mixed;
}
