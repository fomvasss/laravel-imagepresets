# Drivers & formats

## Choosing the driver

Glide runs on GD or Imagick:

```env
IMAGEPRESET_DRIVER=imagick
```

The value is read as `env('IMAGEPRESET_DRIVER', env('IMAGE_DRIVER', 'gd'))`: when `IMAGEPRESET_DRIVER` is not set, the project-wide `IMAGE_DRIVER` is used (the variable spatie/laravel-medialibrary and other packages read), then `gd`.

> [!WARNING]
> If the project already sets `IMAGE_DRIVER=imagick` for another package, presets switch to Imagick too (since 1.19). Set `IMAGEPRESET_DRIVER=gd` to keep GD.

The driver must be installed: `ext-gd` or `ext-imagick`.

## Output formats

| `fm` | GD | Imagick | Notes |
|---|---|---|---|
| `webp` | yes | yes | Default `format` |
| `jpg` | yes | yes | |
| `pjpg` | yes | yes | Progressive JPEG, stored as `.jpg` |
| `png` | yes | yes | `q` has no effect |
| `gif` | yes | yes | `q` has no effect |
| `avif` | if GD is built with AVIF support | if ImageMagick has an AVIF delegate | Not in the default `allowed_formats` |

Only formats listed in `allowed_formats` (default `webp`, `jpg`, `png`, `gif`) can be requested. To serve AVIF, add it and check that the driver can encode it:

```php
'allowed_formats' => ['webp', 'avif', 'jpg', 'png', 'gif'],
```

Responses get the matching `Content-Type` for `webp`, `avif`, `jpg`, `png`, `gif` and `svg`. Any other format Glide can produce (`tiff`, `heic`) is served as `application/octet-stream` — don't add those to `allowed_formats`.

## Input formats

Whatever the driver can decode: JPEG, PNG, GIF, WebP with GD; with Imagick also AVIF, TIFF, and HEIC/HEIF (ImageMagick built with `libheif`). Phone photos in HEIC therefore need Imagick.

SVG input is handled separately, see [SVG](svg.md).

## Checking the environment

```bash
php -r 'print_r(gd_info());'
php -r 'print_r(Imagick::queryFormats("AVIF")); print_r(Imagick::queryFormats("HEIC"));'
```

A format the driver can't encode or decode doesn't raise an error to the client: the request returns 404. With `APP_DEBUG=true` the reason is logged as `[Imagepresets] makeImage failed`.
