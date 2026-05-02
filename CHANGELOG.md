# Changelog

All notable changes to `fomvasss/laravel-imagepresets` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

