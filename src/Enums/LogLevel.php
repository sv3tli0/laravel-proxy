<?php

declare(strict_types=1);

namespace Lararoxy\Enums;

enum LogLevel: string
{
    case None = 'none';
    case Minimal = 'minimal';
    case Standard = 'standard';
    case Full = 'full';
    case Debug = 'debug';
}
