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

### Quality

`q` (or `quality` from config when the request has none) is passed to the encoder of `webp`, `jpg`, `pjpg` and `avif`.

`q=100` with `fm=webp` is not "the best lossy quality": both drivers switch WebP to lossless mode. Nothing is lost to compression, but the file is several times heavier — for a product photo 1468 px wide about 570 KB instead of 75 KB at `q=80`. To get sharper fine details at a moderate size, use `q=90` (about 1.6 times heavier than `q=80`).

> [!WARNING]
> ImageMagick 6 (6.9.12 from the Ubuntu/Debian packages, with libwebp 1.3) ignores the quality of WebP output: `q=30` and `q=90` produce the same file, the same happens with `convert -quality`. JPEG and AVIF are not affected. With `driver = imagick` on such a system WebP comes out at the library's default quality whatever `q` says — keep `gd` for WebP. Check:
>
> ```bash
> php -r '$i = new Imagick(); $i->newPseudoImage(400, 400, "plasma:"); foreach ([30, 90] as $q) { $c = clone $i; $c->setImageFormat("webp"); $c->setImageCompressionQuality($q); echo "q$q: ", strlen($c->getImagesBlob()), PHP_EOL; }'
> ```
>
> Equal sizes mean `q` has no effect.

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
