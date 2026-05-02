# fomvasss/laravel-imagepresets

> 🇺🇦 [Документація українською](README.uk.md)

On-the-fly image resizing, converting and caching for Laravel, powered by [League/Glide](https://glide.thephpleague.com/).

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10+-red)](https://laravel.com/)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-imagepresets.svg)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![Build Status](https://img.shields.io/github/stars/fomvasss/laravel-imagepresets.svg?style=for-the)](https://github.com/fomvasss/laravel-imagepresets)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-imagepresets.svg)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Features

- On-the-fly resize, crop and format conversion (WebP, AVIF, JPG, PNG, GIF)
- Automatic caching of processed images to any Laravel filesystem disk (local, S3, GCS, FTP, etc.)
- Remote disk support (S3 / GCS / FTP) — Glide processes locally, result is uploaded automatically
- SVG passthrough with optional XSS sanitization
- Remote image support with SSRF and image-bomb protection
- Race condition protection via Cache lock (Redis/Memcached)
- Auto-registered route — no manual setup required
- Facade + global helper + Blade directive
- Artisan command `imagepresets:clear`
- Fully configurable via `config/imagepresets.php`

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.1 |
| Laravel | 10 / 11 / 12 / 13 |
| league/glide | ^2.0 \| ^3.0 |

Optional:
- `imagick` PHP extension — required for AVIF output and SVG rasterization
- `enshrined/svg-sanitize` — full SVG sanitization (recommended)

---

## Installation

```bash
composer require fomvasss/laravel-imagepresets
```

The service provider is auto-discovered via Laravel's package discovery.

### Publish configuration

```bash
php artisan vendor:publish --tag=imagepresets-config
```

This creates `config/imagepresets.php` in your application.

---

## Configuration

Key options in `config/imagepresets.php`:

```php
// Route
'route' => [
    'prefix'     => env('IMAGEPRESET_ROUTE_PREFIX', 'imagepresets'),
    'name'       => env('IMAGEPRESET_ROUTE_NAME', 'imagepresets'),
    'middleware' => ['throttle:240,1'],
],

// Storage disk and subdirectory for cached presets
'disk' => env('IMAGEPRESET_DISK', 'public'),  // or 's3', 'gcs', etc.
'path' => env('IMAGEPRESET_PATH', 'imagepresets'),

// Processing driver: 'gd' or 'imagick'
'driver' => env('IMAGEPRESET_DRIVER', 'gd'),

// Default output quality and format
'quality' => 80,
'format'  => 'webp',

// HTTP cache lifetime (seconds)
'cache_max_age' => 31536000,

// Allowed dimensions, qualities, fit methods, formats
// Use ['*'] as a wildcard to allow any value (no restriction)
'allowed_widths'    => [100, 200, 300, 400, 600, 800, 1000, 1200, 1600],
'allowed_heights'   => [100, 200, 300, 400, 600, 800],
'allowed_sizes'     => [[300, 200], [600, 400], [1200, 800]],
'allowed_qualities' => [50, 60, 70, 80, 90, 100],
'allowed_fits'      => ['contain', 'crop', 'fill', 'max', 'stretch'],
'allowed_formats'   => ['webp', 'jpg', 'png', 'gif'],

// SVG optionslaravel-imagepresets
'svg' => [
    'sanitize'                 => true,
    'remove_remote_references' => true,
    'rasterize'                => false, // requires driver=imagick
],

// Remote image protection
'max_download_bytes' => 20 * 1024 * 1024,
'max_image_pixels'   => 150_000_000, // ~150 Mpx image-bomb protection
'allowed_hosts'      => [], // e.g. ['cdn.example.com']

// Local Glide working dir when using a remote disk (S3/GCS/FTP)
'local_cache_dir' => storage_path('app/imagepreset_glide_cache'),
```

> **Cache Lock:** For correct multi-server behaviour set `CACHE_DRIVER=redis` in `.env`.
> The `file` driver only locks within a single PHP process.

### Remote disk (S3 / GCS / FTP)

Set the disk in `.env` — the package detects it automatically:

```ini
IMAGEPRESET_DISK=s3
IMAGEPRESET_PATH=imagepresets
```

Processing flow for remote disks:
1. Glide processes the image into `local_cache_dir` (local)
2. The result is uploaded to the remote disk via Flysystem
3. The local file is deleted
4. The response is streamed directly from the remote disk

```bash
# Clear S3 preset cache
php artisan imagepresets:clear --disk=s3
```

---

## Usage

### Endpoint

```
GET /imagepresets?src=...&w=...&h=...&q=...&fm=...&fit=...
```

### Query parameters

| Parameter | Type | Description |
|---|---|---|
| `src` | string | **Required.** Relative path or remote URL of the source image |
| `preset` | string | Named preset defined in `config/imagepresets.php` (presets section) |
| `w` | int | Output width in pixels (must be in `allowed_widths`, or any if `['*']`) |
| `h` | int | Output height in pixels (must be in `allowed_heights`, or any if `['*']`) |
| `q` | int | Quality 1–100 (must be in `allowed_qualities`, or any if `['*']`) |
| `fm` | string | Output format: `webp`, `jpg`, `png`, `gif`, `avif` |
| `fit` | string | Fit method: `contain`, `crop`, `fill`, `max`, `stretch` |
| `blur` | int | Blur radius `0–100` |
| `sharp` | int | Sharpen amount `0–100` |
| `or` | string | Orientation: `auto` (EXIF), `0`, `90`, `180`, `270` |
| `crop` | string | Coordinate crop: `w,h,x,y` — e.g. `200,200,10,10` |
| `bg` | string | Background fill colour (hex without `#`): `fff`, `ff5733` |

When both `w` and `h` are passed, the pair must be listed in `allowed_sizes` (unless `allowed_sizes = ['*']`).

### Wildcard mode

Set any of the `allowed_*` config keys to `['*']` to disable the corresponding restriction entirely:

```php
'allowed_widths'    => ['*'], // any width is accepted
'allowed_heights'   => ['*'], // any height is accepted
'allowed_sizes'     => ['*'], // any w+h pair is accepted
'allowed_qualities' => ['*'], // any quality value 1–100 is accepted
```

> **Note:** Base HTTP validation limits still apply — `w` and `h` are capped at `20000`, `q` must be an integer.

### Helper function

```php
$url = imagepreset_url('storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepresets?fm=webp&src=storage%2Fimages%2Fphoto.jpg&w=800
```

```php
$url = imagepreset_url('https://example.com/storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepresets?fm=webp&src=https://example.com/storage%2Fimages%2Fphoto.jpg&w=800
```

---

## Named Presets

Define reusable named presets in `config/imagepresets.php`:

```php
'presets' => [
    'thumb'  => ['w' => 300, 'h' => 200, 'fm' => 'webp', 'q' => 80, 'fit' => 'crop'],
    'hero'   => ['w' => 1200, 'fm' => 'webp', 'q' => 85],
    'avatar' => ['w' => 96, 'h' => 96, 'fm' => 'webp', 'fit' => 'crop'],
],
```

Use a preset by name:

```php
// Helper — shorthand string
$url = imagepreset_url('photo.jpg', 'thumb');

// Helper — array key
$url = imagepreset_url('photo.jpg', ['preset' => 'hero']);

// Facade
Imagepresets::url('photo.jpg', 'avatar');

// Blade directive
<img src="@imagepreset('photo.jpg', 'thumb')" alt="Thumbnail">

// HTML endpoint
<img src="/imagepresets?src=photo.jpg&preset=thumb" alt="Thumbnail">
```

Explicit params passed alongside a preset **override** the preset defaults:

```php
// Uses thumb preset but overrides format to jpg
$url = imagepreset_url('photo.jpg', ['preset' => 'thumb', 'fm' => 'jpg']);
```

> **Security:** preset params come from trusted config and bypass `allowed_widths` /
> `allowed_heights` / `allowed_sizes` / `allowed_qualities` checks.
> Explicit override params are still validated against the allowlists.

### Facade

```php
use Fomvasss\Imagepresets\Facades\Imagepresets;

$url = Imagepresets::url('storage/images/photo.jpg', ['w' => 400, 'h' => 300]);
```

### Blade directive

```blade
<img src="@imagepreset('storage/images/photo.jpg', ['w' => 600, 'fm' => 'webp'])" alt="Photo">
```

### HTML example

```html
<img src="/imagepresets?src=storage/images/photo.jpg&w=800&fm=webp" alt="Photo">
```

---

## SVG Support

SVG files are passed through without dimension transformations. The cache key is based solely on `src` to avoid duplicates.

```php
// SVG is cached and served as-is (sanitized by default)
$url = imagepreset_url('storage/icons/logo.svg');
```

Enable sanitization (recommended) in config:

```php
'svg' => [
    'sanitize' => true,
],
```

For full sanitization install the optional dependency:

```bash
composer require enshrined/svg-sanitize
```

Without it, a basic regex sanitizer removes `<script>` tags, `on*` event attributes, and `javascript:` URIs.

### SVG rasterization

To convert SVG to raster when `w`, `h` or `fm` is passed:

```php
'svg' => [
    'rasterize' => true, // requires driver=imagick
],
```

---

## Remote Images

Pass any allowed external URL as `src`:

```php
$url = imagepreset_url('https://cdn.example.com/photo.jpg', ['w' => 400]);
```

Allowed hosts must be declared in config:

```php
'allowed_hosts' => [
    'cdn.example.com',
],
```

**Security measures:**
- HTTP redirects are blocked (SSRF protection)
- Private and reserved IP ranges are rejected
- `localhost` is rejected
- Maximum download size: `max_download_bytes`
- Maximum pixel area: `max_image_pixels` (image-bomb protection)

---

## Artisan Commands

### Clear preset cache

```bash
php artisan imagepresets:clear
```

Options:

| Option | Description |
|---|---|
| `--disk=` | Override disk (default: `imagepresets.disk` config) |
| `--path=` | Override path (default: `imagepresets.path` config) |
| `--temp` | Also clear `source_dir` and `temp_dir` |

```bash
# Clear cache + temp directories
php artisan imagepresets:clear --temp

# Clear a custom disk/path
php artisan imagepresets:clear --disk=s3 --path=presets
```

---

## Response Headers

Every response includes:

| Header | Value |
|---|---|
| `Content-Type` | Correct MIME type |
| `Cache-Control` | `public, max-age=N, s-maxage=N, immutable` |
| `ETag` | Based on file mtime + size |
| `Last-Modified` | File modification time |
| `Content-Disposition` | `inline` |
| `X-Content-Type-Options` | `nosniff` |
| `Content-Security-Policy` | SVG only: `default-src 'none'; style-src 'unsafe-inline'; sandbox` |

---

## Security

| Threat | Protection |
|---|---|
| Path traversal | `..` and null bytes rejected in `src` |
| SSRF via remote URL | Private/reserved IPs + localhost blocked; redirects disabled |
| Image bomb | Pixel area check (`max_image_pixels`) for both local and remote files |
| SVG XSS | Sanitization via `enshrined/svg-sanitize` or regex fallback; CSP header |
| Cache stampede | `Cache::lock()` with double-check after acquiring |
| Content sniffing | `X-Content-Type-Options: nosniff` |

---

## Testing

```bash
composer test
# or
vendor/bin/phpunit
```

---

## License

MIT — see [LICENSE](LICENSE).

