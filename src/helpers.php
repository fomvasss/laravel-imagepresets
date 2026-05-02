<?php

declare(strict_types=1);

if (!function_exists('imagepreset_url')) {
    /**
     * Generates a URL to an image preset.
     *
     * @param  string        $src    Relative path or remote URL of the image.
     * @param  array|string  $params Associative array of params (w, h, q, fit, fm, preset, …)
     *                               OR a named preset string, e.g. 'thumb'.
     */
    function imagepreset_url(string $src, array|string $params = []): string
    {
        return app(\Fomvasss\Imagepresets\Services\ImagepresetService::class)->url($src, $params);
    }
}
