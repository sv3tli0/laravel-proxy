<?php

declare(strict_types=1);

namespace Lararoxy\Enums;

enum TrackingStoreType: string
{
    case Database = 'database';
    case Redis = 'redis';
    case Cache = 'cache';
}
