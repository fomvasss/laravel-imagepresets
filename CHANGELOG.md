# Changelog

All notable changes to `fomvasss/laravel-imagepresets` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.15.0] - 2026-05-30

### Added
- `remote_redirect` config option (`IMAGEPRESET_REMOTE_REDIRECT`) — redirect to presigned S3/GCS URL instead of streaming through PHP; reduces server bandwidth
- `remote_redirect_ttl` config option (`IMAGEPRESET_REMOTE_REDIRECT_TTL`) — presigned URL lifetime in seconds (default: 300); falls back to streaming if disk does not support `temporaryUrl()`

### Changed
- Remote vs local disk detection now uses `driver` config key (`driver === 'local'`) instead of `is_dir(root)` — more reliable for S3/GCS with non-empty root prefix
- `ensureOutputDirectory()` skips `disk->exists()` + `disk->makeDirectory()` for remote disks (S3/GCS have no real directories, avoiding unnecessary API calls)
- `uploadToRemoteDisk()` — local file is now deleted **only after** successful upload; previously deleted even on failure
- SVG responses always use long-term cache headers (`immutable`) — `no-store` was incorrect for a sanitized passthrough that is always valid on first generation
- Removed `findLocalPath()` call from `ImagepresetValidator` — existence check is delegated to the service layer, eliminating double filesystem I/O per request

### Fixed
- `LockTimeoutException` from `Cache::lock()->block()` now returns **503** instead of an unhandled 500 (applies to both raster and SVG processing)

---

## [1.14.0] - 2026-05-30

### Fixed
- Remote HEIC (and other formats unrecognized by `getimagesize()`) no longer rejected in `downloadToTemp()` — now consistent with local file behavior: pixel check is skipped when `getimagesize()` returns `false`, allowing Imagick to handle the format

---

## [1.13.0] - 2026-05-30

### Fixed
- Reverted incorrect `$this->getSourceDir()` call in `GlideProcessor::process()` — method does not exist in that class; restored direct config read

---

## [1.12.0] - 2026-05-30

### Fixed
- Route name fallback inconsistency: `routes/imagepresets.php` fallback was `'imagepresets'` (plural) while `ImagepresetService::url()` used `'imagepreset'` (singular) — both now use `'imagepreset'`
- `auditLog()` no longer calls `resolveDisk()` a second time when `only_new=true` — disk data is now passed as a parameter, eliminating a redundant disk resolution per request
- Redundant `($hasW || $hasH)` condition removed from inside `GlideProcessor::buildParams()` — already guaranteed true by the outer `if`

### Added
- `Cache::lock()` for SVG processing in `processSvg()` — prevents race conditions on concurrent first requests, consistent with raster behavior
- `StreamedResponse` added to `ImagepresetController::__invoke()` return type declaration

---

## [1.10.0] - 2026-05-12

### Added
- Intelligent `Cache-Control` header strategy:
  - **New files** (first generation): `Cache-Control: no-store` — prevents caching of potentially problematic files
  - **Cached files** (subsequent requests): `Cache-Control: public, max-age=31536000, s-maxage=31536000, immutable` — aggressive long-term caching
- Parameter `$isNew` in `ResponseBuilder::build()` and `ResponseBuilder::buildFromDisk()` to control cache headers
- Documentation in README and README.uk.md under "HTTP Caching" section explaining the caching strategy

### Changed
- `ResponseBuilder::build(string $absolutePath, string $ext, bool $isNew = false)` — added `$isNew` parameter
- `ResponseBuilder::buildFromDisk(FilesystemAdapter $disk, string $relPath, string $ext, bool $isNew = false)` — added `$isNew` parameter

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
- Feature tests: `SignedUrlTest`

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
- 8 new tests covering preset validation, URL generation and override behaviour

---

## [1.2.0] - 2026-05-02

### Added
- `blur` param — blur radius `0–100` (`blur_max` config key)
- `sharp` param — sharpen amount `0–100` (`sharp_max` config key)
- `or` param — orientation: `auto` (EXIF auto-rotate), `0`, `90`, `180`, `270` (`allowed_orientations` config key)
- `crop` param — coordinate-based crop `w,h,x,y` (e.g. `200,200,10,10`)
- `bg` param — background fill colour as hex string (e.g. `fff`, `ff5733`); useful for PNG → JPG conversion
- 14 new validation tests covering all new params

---

## [1.0.0] - 2026-05-02

### Added
- Auto-registered route without `web` middleware (no session / CSRF); configurable throttle
- `ImagepresetService` — full pipeline orchestrator: validation → source resolution → processing → HTTP response
- `GlideProcessor` — Glide parameter builder and raster image processor via `league/glide`
- `SvgProcessor` — SVG caching with optional sanitization (`enshrined/svg-sanitize` or regex fallback)
- `SourceResolver` — resolves local and remote sources; same-origin URL shortcut to local file
- `RemoteUrlNormalizer` — canonical URL normalization (scheme/host lowercase, IDN → ASCII, percent-encoding)
- `ImagepresetValidator` — strict request validation with allowlists for sizes, qualities, fits and formats
- `ResponseBuilder` — HTTP response with `Cache-Control`, `ETag`, `Last-Modified`, `Content-Disposition`, `X-Content-Type-Options`, `Content-Security-Policy` (SVG)
- `Facades/Imagepresets` facade and `imagepreset_url()` global helper
- Blade directive `@imagepreset`
- Artisan command `imagepresets:clear` with `--disk`, `--path`, `--temp` options
- SVG rasterization via Imagick when `rasterize=true` and `w`/`h`/`fm` params are present
- Support for `webp`, `jpg`, `png`, `gif`, `avif` output formats; `pjpg`/`jpeg` normalized to `jpg`
- Remote disk support (S3, GCS, FTP, etc.): Glide processes images into `local_cache_dir`,
  uploads the result to the remote disk via Flysystem, removes the local file, and streams
  the response — no permanent local copy is kept
- `ResponseBuilder::buildFromDisk()` — streamed response from any Flysystem disk
- `local_cache_dir` config key — local Glide working directory for remote disk mode
- Automatic disk type detection: local (has `root` in filesystems config) vs remote


### Security
- SSRF protection: private/reserved IP ranges and `localhost` blocked; HTTP redirects disabled (`allow_redirects=false`)
- Image-bomb protection: pixel area check (`max_image_pixels`) applied to both local and remote files
- Race condition protection: `Cache::lock()` with double-check after acquiring (Redis/Memcached recommended)
- SVG XSS protection: sanitization + `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`
- Content sniffing prevention: `X-Content-Type-Options: nosniff` on all responses
- Path traversal prevention: `..` and null bytes rejected in `src` parameter

