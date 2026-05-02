<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Http\Controllers;

use Fomvasss\Imagepresets\Services\ImagepresetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ImagepresetController extends Controller
{
    public function __construct(
        private readonly ImagepresetService $service,
    ) {}

    public function __invoke(Request $request): BinaryFileResponse|Response
    {
        return $this->service->handle($request);
    }
}

