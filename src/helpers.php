<?php

declare(strict_types=1);

if (!function_exists('imagepreset_url')) {
    /**
     * Generates a URL to an image preset.
     *
     * @param  string        $src    Relative path or remote URL of the image.
     * @param  array|string  $params Associative array of params (w, h, q, fit, fm, preset, …)
     *                               OR a named preset string, e.g. 'thumb'.
     * @param  bool          $bypass When true and IMAGEPRESET_TRUSTED_BYPASS is enabled, appends
     *                               a server-signed token that bypasses allowed_* allowlist checks.
     *                               Use in Blade/backend code only — never expose to end users.
     */
    function imagepreset_url(string $src, array|string $params = [], bool $bypass = false): string
    {
        return app(\Fomvasss\Imagepresets\Services\ImagepresetService::class)->url($src, $params, $bypass);
    }
}
