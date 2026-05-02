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
// Використовуйте ['*'] як wildcard для дозволу будь-яких значень (без обмежень)
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
| `preset` | string | Іменований пресет з `config/imagepresets.php` (секція presets) |
| `w` | int | Ширина виводу в пікселях (має бути в `allowed_widths`, або будь-яка якщо `['*']`) |
| `h` | int | Висота виводу в пікселях (має бути в `allowed_heights`, або будь-яка якщо `['*']`) |
| `q` | int | Якість 1–100 (має бути в `allowed_qualities`, або будь-яка якщо `['*']`) |
| `fm` | string | Формат виводу: `webp`, `jpg`, `png`, `gif`, `avif` |
| `fit` | string | Метод вписування: `contain`, `crop`, `fill`, `max`, `stretch` |
| `blur` | int | Розмиття `0–100` |
| `sharp` | int | Різкість `0–100` |
| `or` | string | Орієнтація: `auto` (EXIF), `0`, `90`, `180`, `270` |
| `crop` | string | Обрізання за координатами: `w,h,x,y` — напр. `200,200,10,10` |
| `bg` | string | Фоновий колір (hex без `#`): `fff`, `ff5733` |

При одночасній передачі `w` та `h` — пара має бути в `allowed_sizes` (якщо `allowed_sizes` не рівне `['*']`).

### Wildcard-режим

Встановіть будь-який з конфіг-ключів `allowed_*` у `['*']`, щоб повністю вимкнути відповідне обмеження:

```php
'allowed_widths'    => ['*'], // будь-яка ширина приймається
'allowed_heights'   => ['*'], // будь-яка висота приймається
'allowed_sizes'     => ['*'], // будь-яка пара w+h приймається
'allowed_qualities' => ['*'], // будь-яке значення якості 1–100 приймається
```

> **Примітка:** Базова HTTP-валідація залишається активною — `w` та `h` обмежені максимумом `20000`, `q` має бути цілим числом.

### Helper-функція

```php
$url = imagepreset_url('storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepresets?fm=webp&src=storage%2Fimages%2Fphoto.jpg&w=800
```

```php
$url = imagepreset_url('https://example.com/storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepresets?fm=webp&src=https://example.com/storage%2Fimages%2Fphoto.jpg&w=800
```

---

## Іменовані пресети

Визначте пресети у `config/imagepresets.php`:

```php
'presets' => [
    'thumb'  => ['w' => 300, 'h' => 200, 'fm' => 'webp', 'q' => 80, 'fit' => 'crop'],
    'hero'   => ['w' => 1200, 'fm' => 'webp', 'q' => 85],
    'avatar' => ['w' => 96, 'h' => 96, 'fm' => 'webp', 'fit' => 'crop'],
],
```

Використання пресету за іменем:

```php
// Helper — скорочений рядок
$url = imagepreset_url('photo.jpg', 'thumb');

// Helper — через масив
$url = imagepreset_url('photo.jpg', ['preset' => 'hero']);

// Facade
Imagepresets::url('photo.jpg', 'avatar');

// Blade-директива
<img src="@imagepreset('photo.jpg', 'thumb')" alt="Мініатюра">

// HTML ендпоінт
<img src="/imagepresets?src=photo.jpg&preset=thumb" alt="Мініатюра">
```

Явні параметри поряд з пресетом **мають пріоритет** над параметрами пресету:

```php
// Використовує пресет thumb, але перевизначає формат на jpg
$url = imagepreset_url('photo.jpg', ['preset' => 'thumb', 'fm' => 'jpg']);
```

> **Безпека:** параметри пресету беруться з довіреного конфігу і обходять перевірки
> `allowed_widths` / `allowed_heights` / `allowed_sizes` / `allowed_qualities`.
> Явні override-параметри проходять звичайну валідацію.

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

## Audit Log (виявлення потрібних розмірів)

Використовуйте режим аудиту в `local` / `staging` середовищах, щоб з'ясувати які саме параметри зображень реально запитує фронтенд — а потім перенести їх в явні allowlists для production.

### Робочий процес

1. Увімкніть wildcard-режим та audit log у `.env` (лише поза production):

```ini
IMAGEPRESET_AUDIT_LOG=true
# IMAGEPRESET_AUDIT_LOG_ONLY_NEW=true  # логувати лише cache miss (перша генерація)
```

> Записи йдуть у стандартний канал додатку (`LOG_CHANNEL` з `.env`).

2. Дозвольте фронтенду працювати вільно — кожна нова комбінація параметрів пишеться в лог:

```json
{"message":"imagepreset_request","context":{"params":{"src":"products/photo.jpg","w":640,"fm":"webp"},"ip":"127.0.0.1","url":"http://app.test/imagepresets?src=..."}}
```

3. Проаналізуйте лог, щоб зібрати унікальні комбінації:

```bash
# Всі унікальні значення w
grep -oh '"w":[0-9]*' storage/logs/imagepresets*.log | sort -u

# Всі унікальні пари [w, h]
grep -oh '"w":[0-9]*,"h":[0-9]*' storage/logs/imagepresets*.log | sort -u

# Всі унікальні значення якості
grep -oh '"q":[0-9]*' storage/logs/imagepresets*.log | sort -u
```

4. Перенесіть знахідки в явні allowlists у `config/imagepresets.php` та вимкніть
   wildcard і audit log перед деплоєм на production:

```php
'allowed_widths'    => [320, 640, 960, 1280],
'allowed_heights'   => [200, 400],
'allowed_sizes'     => [[640, 400], [1280, 800]],
'allowed_qualities' => [80, 90],
```

```ini
# .env (production)
IMAGEPRESET_AUDIT_LOG=false
```

### Параметри конфігурації

| Ключ | За замовчуванням | Опис |
|---|---|---|
| `audit_log.enabled` | `false` | Увімкнути через `IMAGEPRESET_AUDIT_LOG` |
| `audit_log.only_new` | `true` | Логувати лише cache miss — пропускати вже кешовані комбінації |

---

## Виключення кешу пресетів з бекапів

Папка кешу `/imagepresets` містить автоматично згенеровані файли, які завжди можна
відновити повторними запитами. Включати їх у бекап — марна трата місця і часу.

### Рекомендовано: окремий диск поза областю бекапу

Визначте окремий filesystem-диск, що знаходиться **за межами** директорії, яка потрапляє в бекап:

```php
// config/filesystems.php
'imagepresets_cache' => [
    'driver' => 'local',
    'root'   => storage_path('app/imagepresets_cache'), // не всередині app/public
    // 'url'    => env('APP_URL').'/imagepresets_cache', // не обовязково, оскільки цей диск не використовується для публічного доступу
    'visibility' => 'public',
    'throw'      => false,
],
```

```ini
# .env
IMAGEPRESET_DISK=imagepresets_cache
```

Тепер папка кешу повністю поза `storage/app/public` і ніколи не потрапить
у бекапи, що включають лише `storage_path('app/public')`.

> **Примітка:** Директорія кешу створюється автоматично при першому запиті до кожного
> пресету — жодних ручних дій після відновлення з бекапу не потрібно.

---

## HTTP-кешування та CDN / Reverse Proxy

Кожна відповідь з ендпоінту `/imagepresets` містить заголовки, оптимізовані для агресивного edge-кешування:

```
Cache-Control: public, max-age=31536000, s-maxage=31536000, immutable
ETag: "<hash>"
Last-Modified: <date>
```

### Nginx

Кешуйте оброблені пресети безпосередньо на сервері — наступні запити оминають Laravel повністю:

```nginx
proxy_cache_path /var/cache/nginx/imagepresets
    levels=1:2
    keys_zone=imagepresets:20m
    max_size=2g
    inactive=365d
    use_temp_path=off;

server {
    # ...

    location /imagepresets {
        proxy_cache            imagepresets;
        proxy_cache_valid      200 365d;
        proxy_cache_use_stale  error timeout updating http_500 http_502 http_503;
        proxy_cache_lock       on;                    # захист від cache stampede
        proxy_cache_key        "$scheme$host$request_uri";
        add_header             X-Cache-Status $upstream_cache_status;

        proxy_pass http://127.0.0.1:9000;             # ваш PHP-FPM / додаток
    }
}
```

### Cloudflare

Додайте Cache Rule у панелі Cloudflare:

- **If** → URI Path starts with `/imagepresets`
- **Then** → Cache Level: Cache Everything, Edge Cache TTL: 1 year

Або через Terraform / API:

```json
{
  "description": "Cache imagepresets",
  "expression": "(http.request.uri.path starts_with \"/imagepresets\")",
  "action": "set_cache_settings",
  "action_parameters": {
    "cache": true,
    "edge_ttl": { "mode": "override_origin", "default": 31536000 },
    "browser_ttl": { "mode": "override_origin", "default": 31536000 }
  }
}
```

### Інвалідація кешу

При зміні визначення пресету або заміні вихідного зображення кешовані файли потрібно очистити:

```bash
# Очистити Laravel-рівень (обов'язково)
php artisan imagepresets:clear

# Nginx — видалити файли proxy-кешу
find /var/cache/nginx/imagepresets -type f -delete

# Cloudflare — purge за префіксом через API
curl -X POST "https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/purge_cache" \
     -H "Authorization: Bearer {TOKEN}" \
     -H "Content-Type: application/json" \
     --data '{"prefixes":["https://example.com/imagepresets"]}'
```

> **Порада:** Використовуйте версіоновані шляхи (`photo_v2.jpg`) або додайте query-параметр
> (`?v=2`), щоб скинути кеш без повного purge.

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

