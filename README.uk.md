# fomvasss/laravel-imagepresets

> 🇬🇧 [English documentation](README.md)

Обробка зображень на льоту: зміна розміру, конвертація та кешування для Laravel на базі [League/Glide](https://glide.thephpleague.com/).

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10+-red)](https://laravel.com/)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-imagepresets.svg)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![Build Status](https://img.shields.io/github/stars/fomvasss/laravel-imagepresets.svg?style=for-the)](https://github.com/fomvasss/laravel-imagepresets)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-imagepresets.svg)](https://packagist.org/packages/fomvasss/laravel-imagepresets)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Можливості

- Зміна розміру, обрізка та конвертація формату на льоту (WebP, AVIF, JPG, PNG, GIF)
- Автоматичне кешування оброблених зображень на будь-який Laravel filesystem disk (local, S3, GCS, FTP тощо)
- Підтримка remote-дисків (S3 / GCS / FTP) — Glide обробляє локально, результат завантажується автоматично
- Підтримка SVG з опціональною XSS-санітизацією
- Підтримка remote-зображень із захистом від SSRF та image-bomb
- Захист від race condition через Cache lock (Redis/Memcached)
- Маршрут реєструється автоматично — жодного ручного налаштування
- Facade + глобальний helper + Blade-директива
- Artisan-команда `imagepresets:clear`
- Повна конфігурація через `config/imagepresets.php`

---

## Вимоги

| Залежність | Версія            |
|---|-------------------|
| PHP | ^8.1              |
| Laravel | 10 / 11 / 12 / 13 |
| league/glide | ^2.0 \| ^3.0      |

Опціонально:
- Розширення `imagick` — необхідне для виводу AVIF та растеризації SVG
- `enshrined/svg-sanitize` — повноцінна SVG-санітизація (рекомендовано)

---

## Встановлення

```bash
composer require fomvasss/laravel-imagepresets
```

Service provider підключається автоматично через Laravel package discovery.

### Публікація конфігурації

```bash
php artisan vendor:publish --tag=imagepresets-config
```

Створює файл `config/imagepresets.php` у вашому додатку.

---

## Конфігурація

Ключові параметри `config/imagepresets.php`:

```php
// Маршрут
'route' => [
    'prefix'     => env('IMAGEPRESET_ROUTE_PREFIX', 'imagepresets'),
    'name'       => env('IMAGEPRESET_ROUTE_NAME', 'imagepresets'),
    'middleware' => ['throttle:240,1'],
],

// Диск та піддиректорія для кешованих пресетів
'disk' => env('IMAGEPRESET_DISK', 'public'),
'path' => env('IMAGEPRESET_PATH', 'imagepresets'),

// Драйвер обробки: 'gd' або 'imagick'
'driver' => env('IMAGEPRESET_DRIVER', 'gd'),

// Якість та формат за замовчуванням
'quality' => 80,
'format'  => 'webp',

// Час HTTP-кешування (секунди)
'cache_max_age' => 31536000,

// Дозволені розміри, якості, методи вписування, формати
'allowed_widths'    => [100, 200, 300, 400, 600, 800, 1000, 1200, 1600],
'allowed_heights'   => [100, 200, 300, 400, 600, 800],
'allowed_sizes'     => [[300, 200], [600, 400], [1200, 800]],
'allowed_qualities' => [50, 60, 70, 80, 90, 100],
'allowed_fits'      => ['contain', 'crop', 'fill', 'max', 'stretch'],
'allowed_formats'   => ['webp', 'jpg', 'png', 'gif'],

// SVG
'svg' => [
    'sanitize'                 => true,
    'remove_remote_references' => true,
    'rasterize'                => false, // потребує driver=imagick
],

// Захист remote-зображень
'max_download_bytes' => 20 * 1024 * 1024,
'max_image_pixels'   => 150_000_000, // ~150 Mpx — захист від image-bomb
'allowed_hosts'      => [], // напр. ['cdn.example.com']

// Локальна робоча директорія Glide при використанні remote-диска (S3/GCS/FTP)
'local_cache_dir' => storage_path('app/imagepreset_glide_cache'),
```

> **Cache Lock:** Для коректної роботи на кількох серверах встановіть `CACHE_DRIVER=redis` у `.env`.
> Драйвер `file` блокує лише в межах одного PHP-процесу.

### Remote-диск (S3 / GCS / FTP)

Вкажіть диск у `.env` — пакет визначає тип автоматично:

```ini
IMAGEPRESET_DISK=s3
IMAGEPRESET_PATH=imagepresets
```

Процес обробки для remote-дисків:
1. Glide обробляє зображення у `local_cache_dir` (локально)
2. Результат завантажується на remote-диск через Flysystem
3. Локальний файл видаляється
4. Відповідь стримується напряму з remote-диска

```bash
# Очистити кеш пресетів на S3
php artisan imagepresets:clear --disk=s3
```

---

## Використання

### Ендпоінт

```
GET /imagepresets?src=...&w=...&h=...&q=...&fm=...&fit=...
```

### Параметри запиту

| Параметр | Тип | Опис |
|---|---|---|
| `src` | string | **Обов'язковий.** Відносний шлях або remote URL вихідного зображення |
| `w` | int | Ширина виводу в пікселях (має бути в `allowed_widths`) |
| `h` | int | Висота виводу в пікселях (має бути в `allowed_heights`) |
| `q` | int | Якість 1–100 (має бути в `allowed_qualities`) |
| `fm` | string | Формат виводу: `webp`, `jpg`, `png`, `gif`, `avif` |
| `fit` | string | Метод вписування: `contain`, `crop`, `fill`, `max`, `stretch` |

При одночасній передачі `w` та `h` — пара має бути в `allowed_sizes`.

### Helper-функція

```php
$url = imagepreset_url('storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepresets?fm=webp&src=storage%2Fimages%2Fphoto.jpg&w=800
```

```php
$url = imagepreset_url('https://example.com/storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepresets?fm=webp&src=https://example.com/storage%2Fimages%2Fphoto.jpg&w=800
```

### Facade

```php
use Fomvasss\Imagepresets\Facades\Imagepresets;

$url = Imagepresets::url('storage/images/photo.jpg', ['w' => 400, 'h' => 300]);
```

### Blade-директива

```blade
<img src="@imagepreset('storage/images/photo.jpg', ['w' => 600, 'fm' => 'webp'])" alt="Фото">
```

### HTML-приклад

```html
<img src="/imagepresets?src=storage/images/photo.jpg&w=800&fm=webp" alt="Фото">
```

---

## Підтримка SVG

SVG-файли передаються без трансформацій розмірів. Кеш-ключ будується лише за `src`, щоб уникнути дублікатів.

```php
// SVG кешується та віддається як є (з санітизацією за замовчуванням)
$url = imagepreset_url('storage/icons/logo.svg');
```

Увімкнути санітизацію (рекомендовано) у конфігу:

```php
'svg' => [
    'sanitize' => true,
],
```

Для повноцінної санітизації встановіть опціональну залежність:

```bash
composer require enshrined/svg-sanitize
```

Без неї застосовується базовий regex-санітайзер: видаляє теги `<script>`, `on*` event атрибути та `javascript:` URI.

### Растеризація SVG

Щоб конвертувати SVG у растр при передачі `w`, `h` або `fm`:

```php
'svg' => [
    'rasterize' => true, // потребує driver=imagick
],
```

---

## Remote-зображення

Передайте будь-який дозволений зовнішній URL як `src`:

```php
$url = imagepreset_url('https://cdn.example.com/photo.jpg', ['w' => 400]);
```

Дозволені хости потрібно оголосити в конфігу:

```php
'allowed_hosts' => [
    'cdn.example.com',
],
```

**Заходи безпеки:**
- HTTP-редиректи заблоковано (захист від SSRF)
- Private та reserved IP-діапазони відхиляються
- `localhost` відхиляється
- Максимальний розмір завантаження: `max_download_bytes`
- Максимальна площа зображення: `max_image_pixels` (захист від image-bomb)

---

## Artisan-команди

### Очищення кешу пресетів

```bash
php artisan imagepresets:clear
```

Опції:

| Опція | Опис |
|---|---|
| `--disk=` | Перевизначити диск (за замовчуванням: `imagepresets.disk`) |
| `--path=` | Перевизначити шлях (за замовчуванням: `imagepresets.path`) |
| `--temp` | Також очистити `source_dir` та `temp_dir` |

```bash
# Очистити кеш + тимчасові директорії
php artisan imagepresets:clear --temp

# Очистити конкретний диск/шлях
php artisan imagepresets:clear --disk=s3 --path=presets
```

---

## Заголовки відповіді

Кожна відповідь містить:

| Заголовок | Значення |
|---|---|
| `Content-Type` | Коректний MIME-тип |
| `Cache-Control` | `public, max-age=N, s-maxage=N, immutable` |
| `ETag` | На основі mtime + розміру файлу |
| `Last-Modified` | Час останньої зміни файлу |
| `Content-Disposition` | `inline` |
| `X-Content-Type-Options` | `nosniff` |
| `Content-Security-Policy` | Лише SVG: `default-src 'none'; style-src 'unsafe-inline'; sandbox` |

---

## Безпека

| Загроза | Захист |
|---|---|
| Path traversal | `..` та null bytes відхиляються в `src` |
| SSRF через remote URL | Блокуються private/reserved IP та localhost; редиректи відключено |
| Image bomb | Перевірка площі пікселів (`max_image_pixels`) для local та remote файлів |
| SVG XSS | Санітизація через `enshrined/svg-sanitize` або regex; CSP заголовок |
| Cache stampede | `Cache::lock()` з подвійною перевіркою після отримання лока |
| Content sniffing | `X-Content-Type-Options: nosniff` |

---

## Тестування

```bash
composer test
# або
vendor/bin/phpunit
```

---

## Ліцензія

MIT — дивіться [LICENSE](LICENSE).

