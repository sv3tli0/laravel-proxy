<?php

declare(strict_types=1);

namespace Lararoxy\Facades;

use Illuminate\Support\Facades\Facade;
use Lararoxy\LararoxyManager;

/**
 * @see LararoxyManager
 */
class Proxy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LararoxyManager::class;
    }
}
