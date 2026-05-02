<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string url(string $src, array $params = [])
 *
 * @see \Fomvasss\Imagepresets\Services\ImagepresetService
 */
final class Imagepresets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'imagepresets';
    }
}

