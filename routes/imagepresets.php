<?php

declare(strict_types=1);

use Fomvasss\Imagepresets\Http\Controllers\ImagepresetController;
use Illuminate\Support\Facades\Route;

$middleware = (array) config('imagepresets.route.middleware', ['throttle:600,1']);

if ((bool) config('imagepresets.route.signed', false)) {
    $middleware[] = 'signed';
}

Route::get(
    '/'.ltrim((string) config('imagepresets.route.prefix', 'imagepresets'), '/'),
    ImagepresetController::class,
)
->middleware($middleware)
->name((string) config('imagepresets.route.name', 'imagepresets'));

