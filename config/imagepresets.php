<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Log (request params logging)
    |--------------------------------------------------------------------------
    | When enabled, every validated request to the imagepresets endpoint is
    | logged to the configured channel. Use this in local/staging environments
    | together with wildcard allowed_* settings to discover which sizes and
    | qualities the frontend actually requests — then promote them to explicit
    | allowlists for production.
    |
    | enabled — set to true (or via env IMAGEPRESET_AUDIT_LOG) to activate.
    | channel — any Laravel log channel defined in config/logging.php.
    |           Recommended: a dedicated 'imagepresets' daily channel so the
    |           audit data stays in a separate file and is easy to analyse.
    | only_new — log only on first generation (cache miss); skip cache hits.
    */
    'audit_log' => [
        'enabled'  => (bool) env('IMAGEPRESET_AUDIT_LOG', false),
        'channel'  => env('IMAGEPRESET_AUDIT_LOG_CHANNEL', 'imagepresets'),
        'only_new' => (bool) env('IMAGEPRESET_AUDIT_LOG_ONLY_NEW', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Named Presets
    |--------------------------------------------------------------------------
    | Define reusable named presets with a fixed set of Glide params.
    | Use a preset name instead of individual params in the helper / Facade:
    |
    |   imagepreset_url('photo.jpg', 'thumb')
    |   imagepreset_url('photo.jpg', ['preset' => 'thumb'])
    |   Imagepresets::url('photo.jpg', 'thumb')
    |   @imagepreset('photo.jpg', 'hero')
    |
    | Supported keys per preset: w, h, q, fm, fit, blur, sharp, or, crop, bg.
    | Explicit request params always override preset defaults.
    |
    | Presets bypass allowed_widths / allowed_heights / allowed_sizes /
    | allowed_qualities checks — values come from trusted config, not user input.
    */
    'presets' => [
        // 'thumb' => ['w' => 300, 'h' => 200, 'fm' => 'webp', 'q' => 80, 'fit' => 'crop'],
        // 'hero'  => ['w' => 1200, 'fm' => 'webp', 'q' => 85],
        // 'avatar'=> ['w' => 96, 'h' => 96, 'fm' => 'webp', 'fit' => 'crop'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    | prefix     — URL prefix for the endpoint (no leading slash).
    | name       — named route used by Facade / helper.
    | middleware — middleware stack. No session/CSRF by default; throttle included.
    */
    'route' => [
        'prefix'     => env('IMAGEPRESET_ROUTE_PREFIX', 'imagepresets'),
        'name'       => env('IMAGEPRESET_ROUTE_NAME', 'imagepresets'),
        'middleware' => ['throttle:240,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Disk & Path
    |--------------------------------------------------------------------------
    | disk — Laravel filesystem disk for storing processed presets.
    | path — subdirectory inside the disk for cached files.
    */
    'disk' => env('IMAGEPRESET_DISK', 'public'),
    'path' => env('IMAGEPRESET_PATH', 'imagepresets'),

    /*
    |--------------------------------------------------------------------------
    | Image Processing Driver
    |--------------------------------------------------------------------------
    | Supported: 'gd', 'imagick'
    */
    'driver' => env('IMAGEPRESET_DRIVER', 'gd'),

    /*
    |--------------------------------------------------------------------------
    | Default Quality & Format
    |--------------------------------------------------------------------------
    | Supported output formats (fm):
    |   webp — GD + Imagick
    |   avif — requires Imagick or GD with libavif support (PHP 8.1+)
    |   jpg  — GD + Imagick
    |   png  — GD + Imagick
    |   gif  — GD + Imagick
    */
    'quality' => 80,
    'format'  => 'webp',

    /*
    |--------------------------------------------------------------------------
    | Default Fit Behaviour
    |--------------------------------------------------------------------------
    | default_fit_both — used when both w and h are provided.
    | default_fit_one  — used when only one dimension is provided.
    */
    'default_fit_both' => 'fill',
    'default_fit_one'  => 'max',

    /*
    |--------------------------------------------------------------------------
    | HTTP Cache
    |--------------------------------------------------------------------------
    | Lifetime in seconds for Cache-Control / s-maxage headers.
    */
    'cache_max_age' => (int) env('IMAGEPRESET_CACHE_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Allowed Dimensions
    |--------------------------------------------------------------------------
    | allowed_sizes   — permitted [w, h] pairs when both dimensions are passed.
    | allowed_widths  — permitted width values when only w is passed.
    | allowed_heights — permitted height values when only h is passed.
    |
    | Wildcard: set any of these to ['*'] to allow any value without restriction.
    | Example:
    |   'allowed_widths'  => ['*'],  // any width  (still capped at max:20000)
    |   'allowed_heights' => ['*'],  // any height (still capped at max:20000)
    |   'allowed_sizes'   => ['*'],  // any w+h pair
    */
    'allowed_sizes' => [
        [300, 200],
        [600, 400],
        [1200, 800],
    ],

    'allowed_widths' => [
        100, 200, 300, 400, 600, 800, 1000, 1200, 1600,
    ],

    'allowed_heights' => [100, 200, 300, 400, 600, 800],

    /*
    |--------------------------------------------------------------------------
    | Allowed Qualities & Fit Methods
    |--------------------------------------------------------------------------
    | allowed_qualities — permitted quality values for the q param.
    |
    | Wildcard: set to ['*'] to allow any integer quality value (1–100).
    | Example:
    |   'allowed_qualities' => ['*'],  // any quality (still validated as integer)
    */
    'allowed_qualities' => [50, 60, 70, 80, 90, 100],
    'allowed_fits'      => ['contain', 'crop', 'fill', 'max', 'stretch'],

    /*
    |--------------------------------------------------------------------------
    | Image Manipulation Params
    |--------------------------------------------------------------------------
    | blur_max            — maximum allowed blur radius (0–100).
    | sharp_max           — maximum allowed sharpen amount (0–100).
    | allowed_orientations — permitted values for the or (orientation) param.
    |                        'auto' reads EXIF data and rotates accordingly.
    |                        Useful for photos taken with a mobile device.
    | crop                — coordinate-based crop: w,h,x,y (e.g. 200,200,10,10).
    |                        Validation format only; Glide enforces image bounds.
    | bg                  — background fill colour as a hex string (3–8 chars).
    |                        Applied when converting transparent PNG → JPG.
    */
    'blur_max'             => 100,
    'sharp_max'            => 100,
    'allowed_orientations' => ['auto', '0', '90', '180', '270'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Output Formats (fm)
    |--------------------------------------------------------------------------
    | Restrict the fm parameter to formats supported by your environment:
    |   webp — GD + Imagick
    |   avif — requires Imagick or GD with libavif (PHP 8.1+)
    |   jpg  — GD + Imagick
    |   png  — GD + Imagick
    |   gif  — GD + Imagick
    */
    'allowed_formats' => ['webp', 'jpg', 'png', 'gif'],

    /*
    |--------------------------------------------------------------------------
    | Temporary Directories
    |--------------------------------------------------------------------------
    | source_dir      — temporary storage for input image copies.
    | temp_dir        — Glide working directory during processing.
    | local_cache_dir — local directory where Glide writes processed files
    |                   when using a remote disk (S3, GCS, FTP, etc.).
    |                   After processing the file is uploaded to the configured
    |                   disk and the local copy is removed automatically.
    |                   Has no effect when disk is local (public / local).
    */
    'source_dir'      => storage_path('app/imagepreset_sources'),
    'temp_dir'        => storage_path('app/imagepreset_temp'),
    'local_cache_dir' => storage_path('app/imagepreset_glide_cache'),

    /*
    |--------------------------------------------------------------------------
    | SVG
    |--------------------------------------------------------------------------
    | sanitize                 — sanitize SVG before caching.
    |                            true  — recommended (XSS protection).
    |                            false — store as-is (trusted sources only).
    |                            For full sanitization install:
    |                              composer require enshrined/svg-sanitize
    |                            Without it a basic regex sanitizer is applied.
    | remove_remote_references — strip external href / xlink:href references.
    | rasterize                — convert SVG → raster (webp/jpg/png) when w/h/fm
    |                            params are present. Requires driver=imagick.
    |                            Falls back to SVG when using gd.
    */
    'svg' => [
        'sanitize'                 => true,
        'remove_remote_references' => true,
        'rasterize'                => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote Image Download
    |--------------------------------------------------------------------------
    | max_download_bytes — maximum response body size for remote src.
    | max_image_pixels   — maximum pixel area (px²) for both local and remote
    |                      images. Protects against image bomb / decompression
    |                      attacks. Set to 0 to disable. Default: 150 Mpx.
    | allowed_hosts      — permitted external hosts (in addition to APP_URL).
    |                      HTTP redirects are blocked (SSRF protection).
    */
    'max_download_bytes' => 20 * 1024 * 1024,
    'max_image_pixels'   => 150_000_000,

    'allowed_hosts' => [
        // 'example.com',
        // 'cdn.example.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Lock (race condition protection)
    |--------------------------------------------------------------------------
    | On the first request for a given preset the package acquires a
    | Cache::lock() to prevent multiple processes from generating the same
    | file simultaneously (cache stampede).
    |
    | IMPORTANT: for correct behaviour across multiple servers/processes the
    | cache driver must support atomic locks — Redis or Memcached.
    | With the file/array driver locking is scoped to a single PHP process.
    |
    | Recommended: CACHE_DRIVER=redis in .env
    */
];
