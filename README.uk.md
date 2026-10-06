# Laravel Image Presets

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-imagepresets.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-imagepresets.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-imagepresets.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-imagepresets)

Зміна розміру, кадрування й конвертація зображень на льоту для Laravel на базі [League Glide](https://glide.thephpleague.com/). Один ендпоінт приймає джерело й параметри в query string, генерує зображення при першому запиті, зберігає на диск і далі віддає збережений файл з довгим кешуванням.

[English](README.md)

Документація англійською — https://fomvasss.github.io/laravel-imagepresets/ (ті самі сторінки, що в [docs/](docs/index.md)).

- **Один ендпоінт, реєструється сам** — без сесії й CSRF, з throttle
- **Білі списки** розмірів, якості, fit і форматів; **іменовані пресети** в конфігу
- **Будь-який диск** — локальний, S3, GCS, FTP; опційно редирект на presigned URL
- **Локальні й віддалені джерела** з захистом від SSRF та image bomb
- Вихід **WebP, AVIF, JPG, PNG, GIF**; GD або Imagick; HEIC на вході з Imagick
- **SVG** — санітизований passthrough або растеризація
- **Підписані URL** і **trusted bypass** для URL, згенерованих бекендом
- `imagepresets:clear` і `imagepresets:verify` для обслуговування кешу
- Хелпер `imagepreset_url()`, фасад `Imagepreset`, Blade-директива `@imagepreset`

## Вимоги

- PHP ^8.1
- Laravel 10 | 11 | 12 | 13
- league/glide ^2.0 | ^3.0, `ext-gd` або `ext-imagick`

## Встановлення

```bash
composer require fomvasss/laravel-imagepresets

php artisan vendor:publish --tag=imagepresets-config
```

```env
IMAGEPRESET_PATH=imagepresets
```

> **Задайте `IMAGEPRESET_PATH`.** З порожнім шляхом за замовчуванням згенеровані файли лягають у корінь диска `public`, а `imagepresets:clear` видаляє весь диск.

## Швидкий старт

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

Розміри поза білими списками (`allowed_widths`, `allowed_sizes`, …) повертають 404 — налаштуйте їх або використовуйте пресети.

## Документація

- [Installation](docs/installation.md) · [Configuration](docs/configuration.md)
- [Generating URLs](docs/usage/generating-urls.md) · [Transformations](docs/usage/transformations.md) · [Named presets](docs/usage/presets.md) · [Allowlists & audit log](docs/usage/allowlists.md)
- [Image sources](docs/usage/sources.md) · [Storage disks](docs/usage/storage.md) · [Drivers & formats](docs/usage/drivers-and-formats.md) · [SVG](docs/usage/svg.md)
- [HTTP caching & CDN](docs/usage/http-caching.md) · [Signed URLs](docs/usage/signed-urls.md) · [Trusted bypass](docs/usage/trusted-bypass.md) · [Cache maintenance](docs/usage/cache-maintenance.md) · [Security](docs/usage/security.md)
- Довідник: [Helper, facade & Blade](docs/reference/api.md) · [Query parameters](docs/reference/query-parameters.md) · [Responses](docs/reference/responses.md) · [Artisan commands](docs/reference/commands.md)
- [Upgrading](docs/upgrading.md) · [Changelog](CHANGELOG.md)

## Ліцензія

MIT — дивись [LICENSE](LICENSE.md).

## Підтримка

Якщо цей пакет є корисним для вас, розгляньте можливість підтримки його розробки:

[![Monobank](https://img.shields.io/badge/Donate-Monobank-black)](https://send.monobank.ua/jar/5xsqtHvVrY)
[![Ko-Fi](https://img.shields.io/badge/Donate-Ko--fi-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/fomvasss)
[![USDT TRC20](https://img.shields.io/badge/Donate-USDT%20TRC20-26A17B?logo=tether&logoColor=white)](https://link.trustwallet.com/send?coin=195&address=THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf&token_id=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t)

> Адреса USDT TRC20: `THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf`
