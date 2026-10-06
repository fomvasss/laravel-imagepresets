# SVG

An `src` is treated as SVG when its path ends in `.svg` (for a URL — the path part, without the query string).

> [!NOTE]
> A remote SVG whose URL has no `.svg` extension (e.g. `https://cdn.example.com/logo?id=5`) is not recognised: it is sent to Glide as a raster image and, unless the driver can read SVG, returns 404.

## Passthrough (default)

SVG is not transformed. It is sanitized (when enabled), stored once and served as `image/svg+xml`:

```php
imagepreset_url('icons/logo.svg');
```

- `w`, `h`, `q`, `fit`, `fm` and the other parameters are validated as usual but not applied.
- The cache file name is the MD5 of `src` only, so every parameter combination for one SVG shares one file.
- The response always has the long-lived `Cache-Control` (never `no-store`) and `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`, so scripts can't run even when the file is opened directly.

## Sanitization

```php
'svg' => [
    'sanitize' => true,
    'remove_remote_references' => true,
    'rasterize' => false,
],
```

With `enshrined/svg-sanitize` installed the full sanitizer is used:

```bash
composer require enshrined/svg-sanitize
```

`remove_remote_references` is passed to it. If it rejects the file, the request returns 404.

Without the package a basic regex filter removes `<script>` blocks, `on*` event attributes and `javascript:` URIs. It doesn't handle remote references or less common vectors — install the sanitizer if SVGs come from users or remote hosts.

`sanitize => false` stores the file as is — only for trusted sources.

## Rasterization

```php
'svg' => [
    'rasterize' => true,
],
```

SVG is converted to a raster image by Glide when all of these hold:

- `svg.rasterize` is `true`;
- `driver` is `imagick` (with GD the SVG is passed through);
- the request (after a preset is applied) has `w`, `h` or `fm`.

The result follows the normal raster rules: `fm` defaults to `format`, allowlists apply, the cache key is the query string. Sanitization is not applied on this path — Imagick reads the original file.
