# Upgrading

Changes within 1.x that can affect existing installations. The full list is in the [CHANGELOG](https://github.com/fomvasss/laravel-imagepresets/blob/master/CHANGELOG.md).

After upgrading compare your published `config/imagepresets.php` with the package's version — new keys fall back to their defaults, but comments and examples are only in the new file.

## 1.19

- 1.19.5: unknown query parameters no longer change the cache key. A URL carrying only image parameters keeps its file name, nothing is regenerated. An extra parameter used as a cache buster (`&v=…`) stops working — rename the source file instead. `q` must be 1–100 even with `allowed_qualities => ['*']`.

- `driver` falls back to the project-wide `IMAGE_DRIVER` when `IMAGEPRESET_DRIVER` is not set. If `IMAGE_DRIVER=imagick` is set for another package, presets silently switch from GD to Imagick. Set `IMAGEPRESET_DRIVER=gd` to keep GD. The published config must contain the new line to pick this up:

  ```php
  'driver' => env('IMAGEPRESET_DRIVER', env('IMAGE_DRIVER', 'gd')),
  ```

- New opt-in `verify_decode` and `imagepresets:verify --deep`, see [Cache maintenance](usage/cache-maintenance.md).

## 1.17

- Generated files are written to a temporary file and renamed after an integrity check. Files broken by earlier crashes stay in the cache — run `php artisan imagepresets:verify` once (local disks only).

## 1.16

- New `trusted_bypass` and the third `$bypass` argument of `imagepreset_url()` / `Imagepreset::url()` / `@imagepreset()`. Off by default.
- Internal fallbacks changed when the config key is missing: route prefix `imagepreset` (was `imagepresets`). Only matters for a published config without `route.prefix`.

## 1.15

- SVG responses always get the long-lived `Cache-Control` (previously `no-store` on first generation).
- On a lock timeout the endpoint answers 503 instead of 500.
- New `remote_redirect` / `remote_redirect_ttl`.

## 1.10

- The response that generates a file has `Cache-Control: no-store`; later responses get the long-lived header. A CDN caches an image from its second request on.

## 1.9 — route prefix and name

The default route prefix and name changed from `imagepresets` to `imagepreset`.

- `route('imagepresets', …)` → `route('imagepreset', …)`.
- Hard-coded `/imagepresets?…` URLs, nginx locations, CDN rules — update to `/imagepreset`.
- Generated files keep working: their names don't depend on the route.

To keep the old URLs:

```env
IMAGEPRESET_ROUTE_PREFIX=imagepresets
IMAGEPRESET_ROUTE_NAME=imagepresets
```

## 1.5

- `audit_log.channel` and `IMAGEPRESET_AUDIT_LOG_CHANNEL` were removed. Audit entries go to the default log channel.
