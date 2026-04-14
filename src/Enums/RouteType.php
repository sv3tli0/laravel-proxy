<?php

declare(strict_types=1);

namespace Lararoxy\Enums;

enum RouteType: string
{
    case Direct = 'direct';
    case Rewrite = 'rewrite';
    case Aggregation = 'aggregation';
    case Wildcard = 'wildcard';
    case MethodOverride = 'method_override';
}
