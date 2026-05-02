<?php

declare(strict_types=1);

if (!function_exists('imagepreset_url')) {
    /**
     * Generates a URL to an image preset.
     *
     * @param  string  $src    Relative path or remote URL of the image.
     * @param  array   $params Additional parameters: w, h, q, fit, fm.
     */
    function imagepreset_url(string $src, array $params = []): string
    {
        return app(\Fomvasss\Imagepresets\Services\ImagepresetService::class)->url($src, $params);
    }
}
