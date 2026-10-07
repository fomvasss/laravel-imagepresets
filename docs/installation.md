# Installation

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.1 |
| Laravel (`illuminate/*`) | 10, 11, 12, 13 |
| league/glide | ^2.0 \| ^3.0 |

Glide needs the `gd` or `imagick` PHP extension. Imagick is required for SVG rasterization and HEIC input, and is the safer choice for AVIF — see [Drivers & formats](usage/drivers-and-formats.md).

Optional: `enshrined/svg-sanitize` for full SVG sanitization (see [SVG](usage/svg.md)).

## Install

```bash
composer require fomvasss/laravel-imagepresets
```

The service provider and the `Imagepreset` facade alias are registered by package discovery. The route, the `@imagepreset` Blade directive and the `imagepreset_url()` helper are available right away.

Publish the config:

```bash
php artisan vendor:publish --tag=imagepresets-config
```

This creates `config/imagepresets.php`. There are no migrations.

## First steps

1. Check the default disk. Processed images go to the `public` disk; with the default empty `path` they land **in the root of that disk**, next to your uploads. Set a subdirectory:

   ```env
   IMAGEPRESET_PATH=imagepresets
   ```

   > [!WARNING]
   > With an empty `path` the cache maintenance commands refuse to run; before 1.19.1 `php artisan imagepresets:clear` deleted the whole disk, not only the generated images. See [Cache maintenance](usage/cache-maintenance.md).

2. Set the allowlists (`allowed_widths`, `allowed_heights`, `allowed_sizes`, …) to the sizes your frontend uses, or define [named presets](usage/presets.md). A request outside the allowlists returns 404.

3. Use a cache store that supports atomic locks across all app servers (Redis, Memcached, database). The package locks each image while it is generated, see [Configuration](configuration.md#cache-lock).

4. Put a CDN or a reverse-proxy cache in front of the endpoint — see [HTTP caching & CDN](usage/http-caching.md).

Check that it works:

```bash
curl -s -o /dev/null -D - "http://your-app.test/imagepreset?src=images/photo.jpg&w=300"
```

`src` is relative to the `public` disk, `storage/` or `public/` — see [Image sources](usage/sources.md).
