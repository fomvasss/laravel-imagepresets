<?php

declare(strict_types=1);

use Fomvasss\Imagepresets\Http\Controllers\ImagepresetController;
use Illuminate\Support\Facades\Route;

Route::get(
    '/'.ltrim((string) config('imagepresets.route.prefix', 'imagepresets'), '/'),
    ImagepresetController::class,
)
->middleware(config('imagepresets.route.middleware', ['throttle:120,1']))
->name((string) config('imagepresets.route.name', 'imagepresets'));

