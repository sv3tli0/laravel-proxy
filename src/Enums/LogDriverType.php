<?php

declare(strict_types=1);

namespace Lararoxy\Enums;

enum LogDriverType: string
{
    case Database = 'database';
    case File = 'file';
    case Custom = 'custom';
}
