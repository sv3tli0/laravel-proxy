<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

interface AuthModel
{
    /**
     * Resolve the TokenPayload class for this model.
     *
     * @return class-string<TokenPayload>
     */
    public function tokenPayloadClass(): string;
}
