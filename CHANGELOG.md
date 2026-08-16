# Changelog

All notable changes to `fomvasss/laravel-imagepresets` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.19.0] - 2026-08-16

### Added
- `imagepresets:verify --deep` — additionally decodes every cached webp and flags files whose VP8 payload is damaged despite a consistent RIFF container size (libwebp fills the undecodable rows with solid rgb(128,128,128)); suspects are listed separately and only removed with the explicit `--delete-suspects` flag since genuinely gray images can be false positives; `--gray-threshold=25` tunes sensitivity
- `verify_decode` config key (`IMAGEPRESET_VERIFY_DECODE`, default `false`) — decode-checks every generated webp before it reaches the cache, so a damaged encode is rejected instead of being cached and served with long-lived immutable headers

### Changed
- `driver` config now falls back to the project-wide `IMAGE_DRIVER` env (shared with spatie/laravel-medialibrary, laravolt/avatar) when `IMAGEPRESET_DRIVER` is not set. Note: deployments that set `IMAGE_DRIVER=imagick` for another package will silently switch presets from gd to imagick after upgrading — set `IMAGEPRESET_DRIVER=gd` explicitly to keep the old behavior

## [1.17.0] - 2026-07-21

### Added
- `imagepresets:verify` artisan command — finds (and with `--delete`, removes) corrupted/truncated cached preset files and orphaned `*.tmp*` leftovers

### Fixed
- Generating a cached image no longer risks leaving a partially-written, broken file being served indefinitely if the process is killed mid-write (OOM, deploy restart, encoder crash) — output is now verified and written atomically

## [1.16.0] - 2026-06-20

### Added
- **Trusted bypass** — backend-generated URLs can skip `allowed_widths` / `allowed_heights` / `allowed_sizes` / `allowed_qualities` / `allowed_fits` / `allowed_formats` allowlist checks via a server-signed HMAC-SHA256 token (`_t`)
- `trusted_bypass` config key (`IMAGEPRESET_TRUSTED_BYPASS`, default `false`) — opt-in per site; leave `false` on public API endpoints
- `$bypass = false` third parameter added to `imagepreset_url()`, `Imagepreset::url()`, and `@imagepreset()` — pass `true` to activate the token for that call
- Token is 16 hex characters, signed with `APP_KEY`; tampering with any URL parameter invalidates it
- `_t` is excluded from the cache key — a trusted and a plain request for the same logical params share the same cached file
- Security checks that are always enforced regardless of the token: path traversal, remote host allowlist, `max_image_pixels`, `w`/`h` max:20000, `fit` requires dimensions

### Fixed
- Enabling `remote_redirect=true` no longer throws a `TypeError` at runtime
- Fallback route prefix (used when not explicitly configured) corrected from `'imagepresets'` to `'imagepreset'`, matching the documented default
- Fallback cache-clear path corrected to match the config default

## [1.15.0] - 2026-05-30

### Added
- `remote_redirect` config option (`IMAGEPRESET_REMOTE_REDIRECT`) — redirect to presigned S3/GCS URL instead of streaming through PHP; reduces server bandwidth
- `remote_redirect_ttl` config option (`IMAGEPRESET_REMOTE_REDIRECT_TTL`) — presigned URL lifetime in seconds (default: 300); falls back to streaming if disk does not support `temporaryUrl()`

### Changed
- More reliable local-vs-remote disk detection for S3/GCS disks with a non-empty root prefix
- Remote disk uploads (S3/GCS) no longer make unnecessary directory-existence API calls
- Local temp file is now deleted only after a successful upload to the remote disk — previously deleted even when the upload failed
- SVG responses always use long-term cache headers (`immutable`) — `no-store` was incorrect for a sanitized passthrough that is always valid on first generation

### Fixed
- A lock-acquisition timeout under heavy concurrent load now returns **503** instead of an unhandled 500 (applies to both raster and SVG processing)

---

## [1.14.0] - 2026-05-30

### Fixed
- Remote HEIC (and other formats `getimagesize()` doesn't recognize) is no longer rejected — now handled by Imagick, consistent with local files

---

## [1.13.0] - 2026-05-30

### Fixed
- Fixed a fatal error during image generation (regression from the previous release)

---

## [1.12.0] - 2026-05-30

### Fixed
- Route name fallback inconsistency — route registration and generated URLs now consistently use `'imagepreset'`

### Added
- `Cache::lock()` for SVG processing — prevents race conditions on concurrent first requests, consistent with raster behavior

---

## [1.10.0] - 2026-05-12

### Added
- Intelligent `Cache-Control` header strategy:
  - **New files** (first generation): `Cache-Control: no-store` — prevents caching of potentially problematic files
  - **Cached files** (subsequent requests): `Cache-Control: public, max-age=31536000, s-maxage=31536000, immutable` — aggressive long-term caching

---

## [1.9.0] - 2026-05-06

### Changed
- Default route `prefix` changed from `imagepresets` to `imagepreset` (env `IMAGEPRESET_ROUTE_PREFIX`)
- Default route `name` changed from `imagepresets` to `imagepreset` (env `IMAGEPRESET_ROUTE_NAME`)

### Migration
- If you rely on the default route name (e.g. `route('imagepresets', ...)`) — update all usages to `route('imagepreset', ...)`
- If you rely on the default URL prefix `/imagepresets` (CDN rules, nginx cache zones, Cloudflare expressions) — update them to `/imagepreset`
- To keep the old behaviour without code changes, set in `.env`:
  ```
  IMAGEPRESET_ROUTE_PREFIX=imagepresets
  IMAGEPRESET_ROUTE_NAME=imagepresets
  ```

---

## [1.6.0] - 2026-05-05

### Added
- Optional Signed URL support (`route.signed` / `IMAGEPRESET_SIGNED_URL`)
- When enabled, `imagepreset_url()`, `Imagepreset::url()` and `@imagepreset()` generate permanent signed URLs via `URL::signedRoute()`
- Requests without a valid signature return 403 Forbidden
- Default: `false` — fully backwards-compatible

---

## [1.5.0] - 2026-05-03

### Changed
- `audit_log.channel` option removed — audit entries are always written to the application default log channel (`LOG_CHANNEL`)
- Env var `IMAGEPRESET_AUDIT_LOG_CHANNEL` removed

---

## [1.4.0] - 2026-05-02

### Added
- Audit log mode (`audit_log.enabled`) — logs every new request params to the application default log channel
- `audit_log.only_new` option — logs only cache misses (first generation), skips already-cached combinations
- Env vars: `IMAGEPRESET_AUDIT_LOG`, `IMAGEPRESET_AUDIT_LOG_ONLY_NEW`
- Workflow documentation: use wildcard + audit log in local/staging to discover required sizes, then promote to explicit allowlists for production

---

## [1.3.0] - 2026-05-02

### Added
- Named presets: define reusable param sets in `config/imagepresets.presets`
- `preset` query param — pass a preset name instead of individual `w`/`h`/`q`/`fm` params
- `imagepreset_url('photo.jpg', 'thumb')` shorthand — preset name as second argument to helper / Facade / Blade directive
- Preset params serve as defaults; explicit request params override them
- Preset params bypass `allowed_widths` / `allowed_heights` / `allowed_sizes` / `allowed_qualities` validation (trusted config source)
- Wildcard support for `allowed_widths`, `allowed_heights`, `allowed_sizes`, `allowed_qualities`: set to `['*']` to allow any value

---

## [1.2.0] - 2026-05-02

### Added
- `blur` param — blur radius `0–100` (`blur_max` config key)
- `sharp` param — sharpen amount `0–100` (`sharp_max` config key)
- `or` param — orientation: `auto` (EXIF auto-rotate), `0`, `90`, `180`, `270` (`allowed_orientations` config key)
- `crop` param — coordinate-based crop `w,h,x,y` (e.g. `200,200,10,10`)
- `bg` param — background fill colour as hex string (e.g. `fff`, `ff5733`); useful for PNG → JPG conversion

---

## [1.0.0] - 2026-05-02

### Added
- Auto-registered route without `web` middleware (no session / CSRF); configurable throttle
- Raster image processing via `league/glide`
- SVG caching with optional sanitization (`enshrined/svg-sanitize` or regex fallback)
- Local and remote image sources supported; same-origin URLs resolve directly to the local file
- Canonical URL normalization (scheme/host lowercase, IDN → ASCII, percent-encoding)
- Strict request validation with allowlists for sizes, qualities, fits and formats
- HTTP responses include `Cache-Control`, `ETag`, `Last-Modified`, `Content-Disposition`, `X-Content-Type-Options`, and `Content-Security-Policy` (SVG) headers
- `Facades/Imagepresets` facade and `imagepreset_url()` global helper
- Blade directive `@imagepreset`
- Artisan command `imagepresets:clear` with `--disk`, `--path`, `--temp` options
- SVG rasterization via Imagick when `rasterize=true` and `w`/`h`/`fm` params are present
- Support for `webp`, `jpg`, `png`, `gif`, `avif` output formats; `pjpg`/`jpeg` normalized to `jpg`
- Remote disk support (S3, GCS, FTP, etc.): images are processed into `local_cache_dir`, uploaded to the remote disk, and streamed back — no permanent local copy is kept
- `local_cache_dir` config key — local working directory for remote disk mode
- Automatic disk type detection: local (has `root` in filesystems config) vs remote

### Security
- SSRF protection: private/reserved IP ranges and `localhost` blocked; HTTP redirects disabled (`allow_redirects=false`)
- Image-bomb protection: pixel area check (`max_image_pixels`) applied to both local and remote files
- Race condition protection: `Cache::lock()` with double-check after acquiring (Redis/Memcached recommended)
- SVG XSS protection: sanitization + `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`
- Content sniffing prevention: `X-Content-Type-Options: nosniff` on all responses
- Path traversal prevention: `..` and null bytes rejected in `src` parameter
