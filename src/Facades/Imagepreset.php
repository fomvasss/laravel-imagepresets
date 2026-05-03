<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string url(string $src, array|string $params = [])
 *
 * @see \Fomvasss\Imagepresets\Services\ImagepresetService
 */
final class Imagepreset extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'imagepresets';
    }
}

