<?php

declare(strict_types=1);

namespace Carabdheer\Carabdheer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Carabdheer\Carabdheer\Carabdheer
 */
class Carabdheer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Carabdheer\Carabdheer\Carabdheer::class;
    }
}
