<?php

declare(strict_types=1);

namespace Lararoxy\Enums;

enum AuthType: string
{
    case Sanctum = 'sanctum';
    case SanctumSpa = 'sanctum-spa';
    case ApiKey = 'api-key';
    case Jwt = 'jwt';
    case Signature = 'signature';
    case OAuth2Client = 'oauth2-client';
    case Bearer = 'bearer';
    case Composite = 'composite';
    case Passthrough = 'passthrough';
    case Custom = 'custom';
}
