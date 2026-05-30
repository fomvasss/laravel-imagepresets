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

## Support

If this package is useful to you, consider supporting its development:

[![Monobank](https://img.shields.io/badge/Donate-Monobank-black)](https://send.monobank.ua/jar/5xsqtHvVrY)
[![Ko-Fi](https://img.shields.io/badge/Donate-Ko--fi-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/fomvasss)
[![USDT TRC20](https://img.shields.io/badge/Donate-USDT%20TRC20-26A17B?logo=tether&logoColor=white)](https://link.trustwallet.com/send?coin=195&address=THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf&token_id=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t)

> USDT TRC20 address: `THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf`

---

## Features

- On-the-fly resize, crop and format conversion (WebP, AVIF, JPG, PNG, GIF)
- HEIC / HEIF input support (requires Imagick + libheif)
- Automatic caching of processed images to any Laravel filesystem disk (local, S3, GCS, FTP, etc.)
- Remote disk support (S3 / GCS / FTP) — Glide processes locally, result is uploaded automatically
- Optional presigned URL redirect for S3/GCS — offloads bandwidth from PHP to the storage provider
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
- `imagick` PHP extension — required for AVIF output, SVG rasterization, and HEIC/HEIF input (requires ImageMagick compiled with `libheif`)
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
    'prefix'     => env('IMAGEPRESET_ROUTE_PREFIX', 'imagepreset'),
    'name'       => env('IMAGEPRESET_ROUTE_NAME', 'imagepreset'),
    'middleware' => ['throttle:240,1'],
],

// Storage disk and subdirectory for cached presets
'disk' => env('IMAGEPRESET_DISK', 'public'),  // or 's3', 'gcs', etc.
'path' => env('IMAGEPRESET_PATH', ''),

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
'allowed_fits'      => ['contain', 'crop', 'fill', 'fill-max', 'max', 'stretch'],
'allowed_formats'   => ['webp', 'jpg', 'png', 'gif'],

// SVG options
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

// Remote disk redirect — redirect to presigned URL instead of streaming through PHP (S3/GCS only)
'remote_redirect'     => env('IMAGEPRESET_REMOTE_REDIRECT', false),
'remote_redirect_ttl' => env('IMAGEPRESET_REMOTE_REDIRECT_TTL', 300), // seconds

// Disable to return the original src URL from helper/facade/directive without processing
'backend_url_enabled' => env('IMAGEPRESET_BACKEND_URL_ENABLED', true),
```

> **Cache Lock:** For correct multi-server behaviour set `CACHE_DRIVER=redis` in `.env`.
> The `file` driver only locks within a single PHP process.

### Remote disk (S3 / GCS / FTP)

Set the disk in `.env` — the package detects local vs remote by the `driver` config key (`local` = local disk, anything else = remote):

```ini
IMAGEPRESET_DISK=s3
IMAGEPRESET_PATH=imagepresets
```

Processing flow for remote disks:
1. Glide processes the image into `local_cache_dir` (local)
2. The result is uploaded to the remote disk via Flysystem
3. The local file is deleted
4. The response is streamed directly from the remote disk (or redirected — see below)

```bash
# Clear S3 preset cache
php artisan imagepresets:clear --disk=s3
```

### Remote redirect (presigned URL)

By default the package streams the processed image through PHP — even from S3/GCS.
Enable redirect mode to offload bandwidth directly to the storage provider:

```ini
# .env
IMAGEPRESET_REMOTE_REDIRECT=true
IMAGEPRESET_REMOTE_REDIRECT_TTL=300  # presigned URL lifetime in seconds (default: 300)
```

When enabled, the package issues a **302 redirect** to a temporary presigned URL
(`temporaryUrl()`) instead of proxying the file through PHP.

**Requirements:**
- The disk driver must support `temporaryUrl()` — S3 and GCS do; FTP does not.
- If the disk does not support temporary URLs, the response automatically falls back to streaming.

**Trade-offs:**

| | Streaming (default) | Redirect (remote_redirect=true) |
|---|---|---|
| PHP bandwidth | Yes | No |
| URL visible to client | No (proxied) | Yes (presigned S3/GCS URL) |
| URL expires | — | After `remote_redirect_ttl` seconds |
| CDN caching | Works | Works (CDN caches the redirect target) |

---

## Usage

### Endpoint

```
GET /imagepreset?src=...&w=...&h=...&q=...&fm=...&fit=...
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
| `fit` | string | Fit method: `contain`, `crop`, `fill`, `fill-max`, `max`, `stretch` |
| `blur` | int | Blur radius `0–100` |
| `sharp` | int | Sharpen amount `0–100` |
| `or` | string | Orientation: `auto` (EXIF), `0`, `90`, `180`, `270` |
| `crop` | string | Coordinate crop: `w,h,x,y` — e.g. `200,200,10,10` |
| `bg` | string | Background fill colour (hex without `#`): `fff`, `ff5733` |

When both `w` and `h` are passed, the pair must be listed in `allowed_sizes` (unless `allowed_sizes = ['*']`).

### Fit methods

| Value | Description |
|---|---|
| `contain` | Scales the image to fit within `w`×`h`, preserving aspect ratio. No cropping. Transparent/empty space is **not** filled. |
| `max` | Same as `contain` but never upscales beyond the original dimensions. |
| `fill` | Scales the image to fill the entire `w`×`h` canvas. Empty space is filled with the `bg` colour. May upscale small images. |
| `fill-max` | Same as `fill` but **never upscales** — if the image is smaller than the canvas it is centred and the remaining space is filled with `bg`. Equivalent to Spatie MediaLibrary's `Fit::FillMax`. |
| `crop` | Scales and **crops** the image to exactly `w`×`h`. No empty space, but edges may be trimmed. |
| `stretch` | Stretches the image to exactly `w`×`h` ignoring the aspect ratio. |

> **`fill-max` vs `crop`:** use `fill-max` when the full image must remain visible (e.g. og:image banners, product feeds); use `crop` when exact pixel dimensions are required and trimming is acceptable.

```php
// The full image is visible; white padding fills the remaining canvas area
$url = imagepreset_url('photo.jpg', ['w' => 1300, 'h' => 650, 'fit' => 'fill-max', 'bg' => 'ffffff', 'fm' => 'jpg']);
```

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
// → https://example.com/imagepreset?fm=webp&src=storage%2Fimages%2Fphoto.jpg&w=800
```

```php
$url = imagepreset_url('https://example.com/storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepreset?fm=webp&src=https://example.com/storage%2Fimages%2Fphoto.jpg&w=800
```

---

## Named Presets

Define reusable named presets in `config/imagepresets.php`:

```php
'presets' => [
    'thumb'  => ['w' => 300, 'h' => 200, 'fm' => 'webp', 'q' => 80, 'fit' => 'crop'],
    'hero'   => ['w' => 1200, 'fm' => 'webp', 'q' => 85],
    'avatar' => ['w' => 96, 'h' => 96, 'fm' => 'webp', 'fit' => 'crop'],

    // og:image social banner — fill-max keeps the full image, fills gaps with bg colour
    'og_banner' => ['w' => 1300, 'h' => 650, 'fit' => 'fill-max', 'fm' => 'jpg', 'q' => 85, 'bg' => 'ffffff'],
],
```

Use a preset by name:

```php
// Helper — shorthand string
$url = imagepreset_url('photo.jpg', 'thumb');

// Helper — array key
$url = imagepreset_url('photo.jpg', ['preset' => 'hero']);

// Facade
Imagepreset::url('photo.jpg', 'avatar');

// Blade directive
<img src="@imagepreset('photo.jpg', 'thumb')" alt="Thumbnail">

// HTML endpoint
<img src="/imagepreset?src=photo.jpg&preset=thumb" alt="Thumbnail">
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
use Fomvasss\Imagepresets\Facades\Imagepreset;

$url = Imagepreset::url('storage/images/photo.jpg', ['w' => 400, 'h' => 300]);
```

### Blade directive

```blade
<img src="@imagepreset('storage/images/photo.jpg', ['w' => 600, 'fm' => 'webp'])" alt="Photo">
```

### HTML example

```html
<img src="/imagepreset?src=storage/images/photo.jpg&w=800&fm=webp" alt="Photo">
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

## Audit Log (discovering required sizes)

Use audit logging in `local` / `staging` environments to discover which image params
the frontend actually requests — then promote them to explicit allowlists for production.

### Workflow

1. Enable wildcard mode + audit log in `.env` (non-production only):

```ini
IMAGEPRESET_AUDIT_LOG=true
# IMAGEPRESET_AUDIT_LOG_ONLY_NEW=true  # log only cache misses (first generation)
```

> Entries are written to the application default log channel (`LOG_CHANNEL` in `.env`).

2. Let the frontend work freely — every new param combination is logged:

```json
{"message":"imagepreset_request","context":{"params":{"src":"products/photo.jpg","w":640,"fm":"webp"},"ip":"127.0.0.1","url":"http://app.test/imagepreset?src=..."}}
```

3. Analyse the log to collect unique combinations:

```bash
# All unique w values requested
grep -oh '"w":[0-9]*' storage/logs/*.log | sort -u

# All unique [w, h] pairs
grep -oh '"w":[0-9]*,"h":[0-9]*' storage/logs/*.log | sort -u

# All unique quality values
grep -oh '"q":[0-9]*' storage/logs/*.log | sort -u
```

4. Promote findings to explicit allowlists in `config/imagepresets.php` and
   disable wildcard + audit log before deploying to production:

```php
'allowed_widths'    => [320, 640, 960, 1280],
'allowed_heights'   => [200, 400],
'allowed_sizes'     => [[640, 400], [1280, 800]],
'allowed_qualities' => [80, 90],
```

```ini
# .env (production)
IMAGEPRESET_AUDIT_LOG=false
```

### Config reference

| Key | Default | Description |
|---|---|---|
| `audit_log.enabled` | `false` | Enable/disable via `IMAGEPRESET_AUDIT_LOG` |
| `audit_log.only_new` | `true` | Log only cache misses — skip already-cached combinations |

---

## Excluding Preset Cache from Backups

The `/imagepreset` cache directory contains auto-generated files that can always be
recreated on demand. Including it in backups wastes storage and increases backup time.

### Recommended: separate disk outside the backup scope

Define a dedicated filesystem disk that lives **outside** your regular backup directory:

```php
// config/filesystems.php
'imagepresets' => [
    'driver' => 'local',
    'root'   => storage_path('app/imagepresets'), // not inside app/public
    // 'url'    => env('APP_URL').'/imagepresets', // no need for a URL since this is only a temporary working dir for Glide
    'visibility' => 'public',
    'throw'      => false,
],
```

```ini
# .env
IMAGEPRESET_DISK=imagepresets
```

Now the cache folder is completely outside `storage/app/public` and will never
appear in backups that include only `storage_path('app/public')`.

> **Note:** The cache directory is recreated automatically on the first request
> for each preset — no manual intervention is needed after a restore.

---

## HTTP Caching & CDN / Reverse Proxy

Every response from the `/imagepreset` endpoint includes headers optimised for aggressive edge caching:

```
Cache-Control: public, max-age=31536000, s-maxage=31536000, immutable
ETag: "<hash>"
Last-Modified: <date>
```

### Nginx

Cache processed presets directly on the server — Laravel is bypassed on subsequent requests:

```nginx
proxy_cache_path /var/cache/nginx/imagepresets
    levels=1:2
    keys_zone=imagepresets:20m
    max_size=2g
    inactive=365d
    use_temp_path=off;

server {
    # ...

    location /imagepreset {
        proxy_cache            imagepresets;
        proxy_cache_valid      200 365d;
        proxy_cache_use_stale  error timeout updating http_500 http_502 http_503;
        proxy_cache_lock       on;                    # prevents cache stampede
        proxy_cache_key        "$scheme$host$request_uri";
        add_header             X-Cache-Status $upstream_cache_status;

        proxy_pass http://127.0.0.1:9000;             # your PHP-FPM / app
    }
}
```

#### Verifying the cache

Make the same request twice using GET — the first returns `MISS`, the second must return `HIT`.
This command works for both **Nginx** (`X-Cache-Status`) and **Cloudflare** (`cf-cache-status`):

```bash
# Sends GET, discards body, prints response headers
curl -s -o /dev/null -D - "https://example.com/imagepreset?src=photo.jpg&w=800&fm=webp"
```

Expected response headers:

```
X-Cache-Status: HIT        # Nginx proxy cache
cf-cache-status: HIT       # Cloudflare edge cache
```

> **Note:** `curl -I` sends a **HEAD** request — Cloudflare does not cache HEAD and always returns
> `cf-cache-status: DYNAMIC`. Always use a GET request to verify caching.

| `X-Cache-Status` | Meaning |
|---|---|
| `MISS` | No cache entry — request went to PHP |
| `HIT` | Served from Nginx cache, PHP was not invoked |
| `EXPIRED` | Cache entry exists but expired — being refreshed |
| `BYPASS` | Caching was skipped |

| `cf-cache-status` | Meaning |
|---|---|
| `MISS` | Cloudflare has no cache — request forwarded to origin |
| `HIT` | Served from Cloudflare edge cache |
| `DYNAMIC` | Not cached — HEAD request or no Cache Rule configured |
| `EXPIRED` | Cache expired — being refreshed from origin |

Compare response times — a `HIT` is typically 10–100× faster:

```bash
time curl -s "https://example.com/imagepreset?src=photo.jpg&w=800" -o /dev/null
```

### Cloudflare

Add a Cache Rule in the Cloudflare dashboard:

- **If** → URI Path starts with `/imagepresets`
- **Then** → Cache Level: Cache Everything, Edge Cache TTL: 1 year

Or via Terraform / API:

```json
{
  "description": "Cache imagepresets",
  "expression": "(http.request.uri.path starts_with \"/imagepreset\")",
  "action": "set_cache_settings",
  "action_parameters": {
    "cache": true,
    "edge_ttl": { "mode": "override_origin", "default": 31536000 },
    "browser_ttl": { "mode": "override_origin", "default": 31536000 }
  }
}
```

### Cache invalidation

When you change a preset definition or replace a source image, the cached files must be purged:

```bash
# Clear the Laravel-level disk cache (always required)
php artisan imagepresets:clear

# Nginx — reload or flush proxy cache directory
find /var/cache/nginx/imagepresets -type f -delete

# Cloudflare — purge by prefix via API
curl -X POST "https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/purge_cache" \
     -H "Authorization: Bearer {TOKEN}" \
     -H "Content-Type: application/json" \
     --data '{"prefixes":["https://example.com/imagepreset"]}'
```

> **Tip:** Use versioned `src` paths (e.g. `photo_v2.jpg`) or append a query param
> (`?v=2`) to bust the cache without a full purge.

---

## Response Headers

Every response includes:

| Header | Value |
|---|---|
| `Content-Type` | Correct MIME type |
| `Cache-Control` | See [HTTP Caching](#http-caching) section below |
| `ETag` | Based on file mtime + size |
| `Last-Modified` | File modification time |
| `Content-Disposition` | `inline` |
| `X-Content-Type-Options` | `nosniff` |
| `Content-Security-Policy` | SVG only: `default-src 'none'; style-src 'unsafe-inline'; sandbox` |

### HTTP Caching

The package implements **intelligent cache headers** based on file generation state:

**Newly generated files (first request):**
```
Cache-Control: no-store
```
- File was just created — may have issues
- Prevents aggressive browser/CDN caching
- Next request will re-validate

**Cached files (subsequent requests):**
```
Cache-Control: public, max-age=31536000, s-maxage=31536000, immutable
```
- File exists and is stable
- URL contains MD5 hash of all parameters — content never changes for same URL
- Safe to cache for 1 year in browser and CDN (Cloudflare, Fastly, Akamai, etc.)
- `immutable` signals the URL is content-addressed — file will never change

**Configuration:**

```php
// config/imagepresets.php
'cache_max_age' => env('IMAGEPRESET_CACHE_MAX_AGE', 31536000),  // 1 year in seconds
```

This approach minimizes bandwidth and server load while maintaining reliability during the generation phase.

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
| Arbitrary URL generation | Optional Signed URL (see below) |

---

## Signed URL (optional)

By default the endpoint is open — any request that passes whitelist validation is processed.
Enable Signed URL to restrict access **only** to URLs generated by the server.

### Enable

```ini
# .env
IMAGEPRESET_SIGNED_URL=true
```

Or in `config/imagepresets.php`:

```php
'route' => [
    // ...
    'signed' => true,
],
```

### How it works

- `imagepreset_url()`, `Imagepreset::url()` and `@imagepreset()` automatically generate
  a permanent signed URL via `URL::signedRoute()`.
- The `signed` middleware validates the HMAC signature on every request.
- Requests without a valid signature return **403 Forbidden**.
- Tampering with any parameter (e.g. changing `w=300` to `w=9999`) invalidates the signature.

### Example

```php
// Generates: https://example.com/imagepreset?fm=webp&signature=...&src=photo.jpg&w=800
$url = imagepreset_url('photo.jpg', ['w' => 800, 'fm' => 'webp']);
```

```blade
<img src="@imagepreset('photo.jpg', ['w' => 600, 'fm' => 'webp'])" alt="Photo">
```

### Notes

- Signed URLs are **permanent** (no expiry). This makes them fully compatible with
  CDN / reverse-proxy caching — no extra CDN configuration required.
- When `signed = false` (default) behaviour is identical to previous versions — **fully backwards-compatible**.
- The signature is based on `APP_KEY`. Rotating the key invalidates all previously generated URLs.

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

