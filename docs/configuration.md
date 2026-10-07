# Configuration

All options live in `config/imagepresets.php` (publish it with `php artisan vendor:publish --tag=imagepresets-config`). Keys without an env variable are changed in the file.

## Route

| Key | Env | Default | Description |
|---|---|---|---|
| `route.prefix` | `IMAGEPRESET_ROUTE_PREFIX` | `imagepreset` | URL path of the endpoint, without leading slash |
| `route.name` | `IMAGEPRESET_ROUTE_NAME` | `imagepreset` | Route name used by the helper, facade and Blade directive |
| `route.middleware` | `IMAGEPRESET_THROTTLE` | `['throttle:2400,1']` | Middleware of the route. The env variable sets the single default entry; edit the array to add more |
| `route.signed` | `IMAGEPRESET_SIGNED_URL` | `false` | Generate signed URLs and add the `signed` middleware, see [Signed URLs](usage/signed-urls.md) |

The route is registered when the application boots, so changing `route.*` at runtime (e.g. in a service provider's `boot()` after the package) has no effect on the route itself. The route has no `web` middleware: no session, no cookies, no CSRF.

## Storage

| Key | Env | Default | Description |
|---|---|---|---|
| `disk` | `IMAGEPRESET_DISK` | `public` | Filesystem disk for generated images |
| `path` | `IMAGEPRESET_PATH` | `''` | Subdirectory inside the disk. Empty — the root of the disk |
| `remote_redirect` | `IMAGEPRESET_REMOTE_REDIRECT` | `false` | On a remote disk, redirect to `temporaryUrl()` instead of streaming the file through PHP |
| `remote_redirect_ttl` | `IMAGEPRESET_REMOTE_REDIRECT_TTL` | `300` | Lifetime of the presigned URL, seconds |
| `local_cache_dir` | — | `storage/app/imagepreset_glide_cache` | Where Glide writes the result before it is uploaded to a remote disk. Not used for local disks |
| `source_dir` | — | `storage/app/imagepreset_sources` | Working copies of source images and downloaded remote images |
| `temp_dir` | — | `storage/app/imagepreset_temp` | Glide's temporary directory |

> [!WARNING]
> Keep `path` non-empty. With `''` the generated files are mixed with whatever else is on the disk, and `imagepresets:clear` / `imagepresets:verify` refuse to run. Before 1.19.1 they worked on the whole disk — `clear` deleted everything on it.

A disk counts as local when its `driver` in `config/filesystems.php` is `local`; anything else is remote. Details — [Storage disks](usage/storage.md).

## Processing

| Key | Env | Default | Description |
|---|---|---|---|
| `driver` | `IMAGEPRESET_DRIVER`, then `IMAGE_DRIVER` | `gd` | `gd` or `imagick` |
| `quality` | — | `80` | Quality when the request has no `q` |
| `format` | — | `webp` | Output format when the request has no `fm` |
| `default_fit_both` | — | `fill` | `fit` when both `w` and `h` are given and `fit` is not |
| `default_fit_one` | — | `max` | `fit` when only `w` or only `h` is given |
| `verify_decode` | `IMAGEPRESET_VERIFY_DECODE` | `false` | Decode every generated webp with GD and reject damaged output before it is cached, see [Cache maintenance](usage/cache-maintenance.md#verify-decode-at-generation-time) |

`driver` reads `IMAGEPRESET_DRIVER` first and falls back to the project-wide `IMAGE_DRIVER` (also used by spatie/laravel-medialibrary and others). See [Drivers & formats](usage/drivers-and-formats.md).

## Allowlists

| Key | Default | Description |
|---|---|---|
| `allowed_sizes` | `[[300, 200], [600, 400], [1200, 800]]` | `[w, h]` pairs allowed when both are given |
| `allowed_widths` | `[100, 200, 300, 400, 600, 800, 1000, 1200, 1600]` | Widths allowed when only `w` is given |
| `allowed_heights` | `[100, 200, 300, 400, 600, 800]` | Heights allowed when only `h` is given |
| `allowed_qualities` | `[50, 60, 70, 80, 90, 100]` | Allowed `q` |
| `allowed_fits` | `['contain', 'crop', 'fill', 'fill-max', 'max', 'stretch']` | Allowed `fit` |
| `allowed_formats` | `['webp', 'jpg', 'png', 'gif']` | Allowed `fm`. `avif` is not in the default list |
| `allowed_orientations` | `['auto', '0', '90', '180', '270']` | Allowed `or` |
| `blur_max` | `100` | Maximum `blur` |
| `sharp_max` | `100` | Maximum `sharp` |

`allowed_sizes`, `allowed_widths`, `allowed_heights` and `allowed_qualities` accept the wildcard `['*']`. `allowed_fits` and `allowed_formats` don't. Details — [Allowlists & audit log](usage/allowlists.md).

## Named presets

| Key | Default | Description |
|---|---|---|
| `presets` | `[]` (examples commented out) | Named parameter sets, see [Named presets](usage/presets.md) |

## Remote sources

| Key | Default | Description |
|---|---|---|
| `allowed_hosts` | `[]` | Hosts allowed in a remote `src`, exact match. The host of `APP_URL` is always allowed |
| `max_download_bytes` | `20971520` (20 MB) | Maximum size of a downloaded remote image |
| `max_image_pixels` | `150000000` | Maximum width × height of a source image, local or remote. `0` — no limit |

Details — [Image sources](usage/sources.md).

## SVG

| Key | Default | Description |
|---|---|---|
| `svg.sanitize` | `true` | Sanitize SVG before caching |
| `svg.remove_remote_references` | `true` | Strip external references; used only with `enshrined/svg-sanitize` |
| `svg.rasterize` | `false` | Convert SVG to raster when `w`, `h` or `fm` is given. Needs `driver = imagick` |

Details — [SVG](usage/svg.md).

## HTTP cache

| Key | Env | Default | Description |
|---|---|---|---|
| `cache_max_age` | `IMAGEPRESET_CACHE_MAX_AGE` | `31536000` | `max-age` and `s-maxage` of served images, seconds |

Details — [HTTP caching & CDN](usage/http-caching.md).

## URL generation and bypass

| Key | Env | Default | Description |
|---|---|---|---|
| `backend_url_enabled` | `IMAGEPRESET_BACKEND_URL_ENABLED` | `true` | `false` — the helper, facade and Blade directive return `src` unchanged. The endpoint keeps working |
| `trusted_bypass` | `IMAGEPRESET_TRUSTED_BYPASS` | `false` | Let backend-generated URLs skip the allowlists, see [Trusted bypass](usage/trusted-bypass.md) |

> [!NOTE]
> With `backend_url_enabled = false` the helper returns `src` exactly as passed — a relative path such as `images/photo.jpg` is not turned into an asset URL.

## Audit log

| Key | Env | Default | Description |
|---|---|---|---|
| `audit_log.enabled` | `IMAGEPRESET_AUDIT_LOG` | `false` | Log the parameters of every valid request to the default log channel |
| `audit_log.only_new` | `IMAGEPRESET_AUDIT_LOG_ONLY_NEW` | `true` | Log only requests whose image is not cached yet |

Details — [Allowlists & audit log](usage/allowlists.md#audit-log).

## Cache lock

There is no config key for it, but it matters: while an image is generated the package holds `Cache::lock('imagepreset:<file>', 30)` and waits up to 15 seconds for it, so concurrent first requests for the same image generate it once. A request that can't get the lock in 15 seconds gets 503.

The lock uses the default cache store. It must support atomic locks and be shared by every app server: Redis, Memcached, database or DynamoDB. The `file` store locks only within one server, `array` only within one process.
