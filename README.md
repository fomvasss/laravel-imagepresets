# Laravel Image Presets

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-imagepresets.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-imagepresets.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-imagepresets.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-imagepresets)

On-the-fly image resizing, cropping and format conversion for Laravel, powered by [League Glide](https://glide.thephpleague.com/). One endpoint takes the source and the transformation in the query string, generates the image on the first request, stores it on a filesystem disk and serves the stored file with long-lived cache headers afterwards.

[Українською](README.uk.md)

- **One auto-registered endpoint** — no session, no CSRF, throttled
- **Allowlists** for sizes, qualities, fits and formats; **named presets** in config
- **Any disk** — local, S3, GCS, FTP; optional redirect to a presigned URL
- **Local and remote sources** with SSRF and image-bomb checks
- **WebP, AVIF, JPG, PNG, GIF** output; GD or Imagick; HEIC input with Imagick
- **SVG** — sanitized passthrough or rasterization
- **Signed URLs** and **trusted bypass** for backend-generated URLs
- `imagepresets:clear` and `imagepresets:verify` for cache maintenance
- `imagepreset_url()` helper, `Imagepreset` facade, `@imagepreset` Blade directive

## Requirements

- PHP ^8.1
- Laravel 10 | 11 | 12 | 13
- league/glide ^2.0 | ^3.0, `ext-gd` or `ext-imagick`

## Installation

```bash
composer require fomvasss/laravel-imagepresets

php artisan vendor:publish --tag=imagepresets-config
```

```env
IMAGEPRESET_PATH=imagepresets
```

> **Set `IMAGEPRESET_PATH`.** With the default empty path generated files land in the root of the `public` disk, and `imagepresets:clear` / `imagepresets:verify` refuse to run (before 1.19.1 `clear` deleted the whole disk).

## Quick start

```php
// config/imagepresets.php
'presets' => [
    'thumb' => ['w' => 300, 'h' => 200, 'fit' => 'crop', 'fm' => 'webp', 'q' => 80],
],
```

```blade
<img src="@imagepreset('images/photo.jpg', 'thumb')" alt="">
<img src="{{ imagepreset_url('images/photo.jpg', ['w' => 600, 'fm' => 'webp']) }}" alt="">
```

```html
<img src="/imagepreset?src=images/photo.jpg&w=600&fm=webp" alt="">
```

Sizes outside the allowlists (`allowed_widths`, `allowed_sizes`, …) return 404 — configure them or use presets.

## Documentation

Online: **https://fomvasss.github.io/laravel-imagepresets/** — the same pages as in [docs/](docs/index.md).

- [Installation](docs/installation.md) · [Configuration](docs/configuration.md)
- [Generating URLs](docs/usage/generating-urls.md) · [Transformations](docs/usage/transformations.md) · [Named presets](docs/usage/presets.md) · [Allowlists & audit log](docs/usage/allowlists.md)
- [Image sources](docs/usage/sources.md) · [Storage disks](docs/usage/storage.md) · [Drivers & formats](docs/usage/drivers-and-formats.md) · [SVG](docs/usage/svg.md)
- [HTTP caching & CDN](docs/usage/http-caching.md) · [Signed URLs](docs/usage/signed-urls.md) · [Trusted bypass](docs/usage/trusted-bypass.md) · [Cache maintenance](docs/usage/cache-maintenance.md) · [Security](docs/usage/security.md)
- Reference: [Helper, facade & Blade](docs/reference/api.md) · [Query parameters](docs/reference/query-parameters.md) · [Responses](docs/reference/responses.md) · [Artisan commands](docs/reference/commands.md)
- [Upgrading](docs/upgrading.md) · [Changelog](CHANGELOG.md)

## Testing

```bash
composer test
```

## License

MIT — see [LICENSE](LICENSE.md).

## Support

If this package is useful to you, consider supporting its development:

[![Monobank](https://img.shields.io/badge/Donate-Monobank-black)](https://send.monobank.ua/jar/5xsqtHvVrY)
[![Ko-Fi](https://img.shields.io/badge/Donate-Ko--fi-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/fomvasss)
[![USDT TRC20](https://img.shields.io/badge/Donate-USDT%20TRC20-26A17B?logo=tether&logoColor=white)](https://link.trustwallet.com/send?coin=195&address=THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf&token_id=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t)

> USDT TRC20: `THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf`
