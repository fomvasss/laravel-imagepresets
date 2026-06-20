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

## Підтримка

Якщо цей пакет є корисним для вас, розгляньте можливість підтримки його розробки:

[![Monobank](https://img.shields.io/badge/Donate-Monobank-black)](https://send.monobank.ua/jar/5xsqtHvVrY)
[![Ko-Fi](https://img.shields.io/badge/Donate-Ko--fi-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/fomvasss)
[![USDT TRC20](https://img.shields.io/badge/Donate-USDT%20TRC20-26A17B?logo=tether&logoColor=white)](https://link.trustwallet.com/send?coin=195&address=THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf&token_id=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t)

> Адреса USDT TRC20: `THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf`

---

## Можливості

- Зміна розміру, обрізка та конвертація формату на льоту (WebP, AVIF, JPG, PNG, GIF)
- Підтримка HEIC / HEIF як вхідного формату (потребує Imagick + libheif)
- Автоматичне кешування оброблених зображень на будь-який Laravel filesystem disk (local, S3, GCS, FTP тощо)
- Підтримка remote-дисків (S3 / GCS / FTP) — Glide обробляє локально, результат завантажується автоматично
- Опціональний redirect на presigned URL для S3/GCS — знімає трафік з PHP на сховище
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
- Розширення `imagick` — необхідне для виводу AVIF, растеризації SVG та обробки HEIC/HEIF (потребує ImageMagick, зібраного з `libheif`)
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
    'prefix'     => env('IMAGEPRESET_ROUTE_PREFIX', 'imagepreset'),
    'name'       => env('IMAGEPRESET_ROUTE_NAME', 'imagepreset'),
    'middleware' => [env('IMAGEPRESET_THROTTLE', 'throttle:2400,1')],
],

// Диск та піддиректорія для кешованих пресетів
'disk' => env('IMAGEPRESET_DISK', 'public'),
'path' => env('IMAGEPRESET_PATH', ''),

// Драйвер обробки: 'gd' або 'imagick'
'driver' => env('IMAGEPRESET_DRIVER', 'gd'),

// Якість та формат за замовчуванням
'quality' => 80,
'format'  => 'webp',

// Метод вписування за замовчуванням: коли передано w+h / лише один розмір
'default_fit_both' => 'fill',
'default_fit_one'  => 'max',

// Час HTTP-кешування (секунди)
'cache_max_age' => 31536000,

// Дозволені розміри, якості, методи вписування, формати
// Використовуйте ['*'] як wildcard для дозволу будь-яких значень (без обмежень)
'allowed_widths'    => [100, 200, 300, 400, 600, 800, 1000, 1200, 1600],
'allowed_heights'   => [100, 200, 300, 400, 600, 800],
'allowed_sizes'     => [[300, 200], [600, 400], [1200, 800]],
'allowed_qualities' => [50, 60, 70, 80, 90, 100],
'allowed_fits'      => ['contain', 'crop', 'fill', 'fill-max', 'max', 'stretch'],
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

// Remote-редирект — redirect на presigned URL замість стримінгу через PHP (лише S3/GCS)
'remote_redirect'     => env('IMAGEPRESET_REMOTE_REDIRECT', false),
'remote_redirect_ttl' => env('IMAGEPRESET_REMOTE_REDIRECT_TTL', 300), // секунди

// Вимкнути, щоб helper/facade/директива повертали оригінальний src без обробки
'backend_url_enabled' => env('IMAGEPRESET_BACKEND_URL_ENABLED', true),
```

> **Cache Lock:** Для коректної роботи на кількох серверах встановіть `CACHE_DRIVER=redis` у `.env`.
> Драйвер `file` блокує лише в межах одного PHP-процесу.

### Remote-диск (S3 / GCS / FTP)

Вкажіть диск у `.env` — пакет визначає local vs remote за ключем `driver` у конфізі (`local` = локальний диск, будь-яке інше значення = remote):

```ini
IMAGEPRESET_DISK=s3
IMAGEPRESET_PATH=imagepresets
```

Процес обробки для remote-дисків:
1. Glide обробляє зображення у `local_cache_dir` (локально)
2. Результат завантажується на remote-диск через Flysystem
3. Локальний файл видаляється
4. Відповідь стримується напряму з remote-диска (або робиться редирект — дивіться нижче)

```bash
# Очистити кеш пресетів на S3
php artisan imagepresets:clear --disk=s3
```

### Remote redirect (presigned URL)

За замовчуванням пакет стримує оброблене зображення через PHP — навіть з S3/GCS.
Увімкніть redirect-режим, щоб передавати трафік напряму зі сховища:

```ini
# .env
IMAGEPRESET_REMOTE_REDIRECT=true
IMAGEPRESET_REMOTE_REDIRECT_TTL=300  # час дії presigned URL у секундах (за замовчуванням: 300)
```

При увімкненні пакет виконує **302 redirect** на тимчасовий presigned URL
(`temporaryUrl()`) замість того, щоб проксювати файл через PHP.

**Вимоги:**
- Драйвер диска має підтримувати `temporaryUrl()` — S3 та GCS підтримують; FTP — ні.
- Якщо диск не підтримує тимчасові URL — відповідь автоматично повертається до стримінгу.

**Порівняння режимів:**

| | Стримінг (за замовчуванням) | Redirect (remote_redirect=true) |
|---|---|---|
| Трафік через PHP | Так | Ні |
| URL сховища видимий клієнту | Ні (проксюється) | Так (presigned S3/GCS URL) |
| URL протермінується | — | Через `remote_redirect_ttl` секунд |
| CDN-кешування | Працює | Працює (CDN кешує ціль редиректу) |

---

## Використання

### Ендпоінт

```
GET /imagepreset?src=...&w=...&h=...&q=...&fm=...&fit=...
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
| `fit` | string | Метод вписування: `contain`, `crop`, `fill`, `fill-max`, `max`, `stretch` |
| `blur` | int | Розмиття `0–100` |
| `sharp` | int | Різкість `0–100` |
| `or` | string | Орієнтація: `auto` (EXIF), `0`, `90`, `180`, `270` |
| `crop` | string | Обрізання за координатами: `w,h,x,y` — напр. `200,200,10,10` |
| `bg` | string | Фоновий колір (hex без `#`): `fff`, `ff5733` |

При одночасній передачі `w` та `h` — пара має бути в `allowed_sizes` (якщо `allowed_sizes` не рівне `['*']`).

### Методи вписування (fit)

| Значення | Опис |
|---|---|
| `contain` | Масштабує зображення, щоб воно вміщалось у `w`×`h`, зберігаючи пропорції. Без обрізки. Порожній простір **не заповнюється**. |
| `max` | Аналогічно `contain`, але ніколи не збільшує зображення понад оригінальний розмір. |
| `fill` | Масштабує зображення для заповнення всього `w`×`h` canvas. Порожній простір заповнюється кольором `bg`. Може збільшувати маленькі зображення. |
| `fill-max` | Аналогічно `fill`, але **ніколи не збільшує** — якщо зображення менше за canvas, воно центрується, а решта заповнюється `bg`. Еквівалент `Fit::FillMax` з Spatie MediaLibrary. |
| `crop` | Масштабує та **обрізає** зображення до точного `w`×`h`. Без порожнього простору, але краї можуть бути обрізані. |
| `stretch` | Розтягує зображення до точного `w`×`h`, ігноруючи пропорції. |

> **`fill-max` vs `crop`:** використовуйте `fill-max`, коли зображення має залишатись повністю видимим (наприклад, og:image банери, товарні фіди); `crop` — коли потрібні точні розміри в пікселях і обрізка прийнятна.

```php
// Повне зображення видиме; білий паддінг заповнює решту canvas
$url = imagepreset_url('photo.jpg', ['w' => 1300, 'h' => 650, 'fit' => 'fill-max', 'bg' => 'ffffff', 'fm' => 'jpg']);
```

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
// → https://example.com/imagepreset?fm=webp&src=storage%2Fimages%2Fphoto.jpg&w=800
```

```php
$url = imagepreset_url('https://example.com/storage/images/photo.jpg', ['w' => 800, 'fm' => 'webp']);
// → https://example.com/imagepreset?fm=webp&src=https://example.com/storage%2Fimages%2Fphoto.jpg&w=800
```

---

## Іменовані пресети

Визначте пресети у `config/imagepresets.php`:

```php
'presets' => [
    'thumb'  => ['w' => 300, 'h' => 200, 'fm' => 'webp', 'q' => 80, 'fit' => 'crop'],
    'hero'   => ['w' => 1200, 'fm' => 'webp', 'q' => 85],
    'avatar' => ['w' => 96, 'h' => 96, 'fm' => 'webp', 'fit' => 'crop'],

    // og:image банер — fill-max зберігає повне зображення, заповнює порожнє місце фоном
    'og_banner' => ['w' => 1300, 'h' => 650, 'fit' => 'fill-max', 'fm' => 'jpg', 'q' => 85, 'bg' => 'ffffff'],
],
```

Використання пресету за іменем:

```php
// Helper — скорочений рядок
$url = imagepreset_url('photo.jpg', 'thumb');

// Helper — через масив
$url = imagepreset_url('photo.jpg', ['preset' => 'hero']);

// Facade
Imagepreset::url('photo.jpg', 'avatar');

// Blade-директива
<img src="@imagepreset('photo.jpg', 'thumb')" alt="Мініатюра">

// HTML ендпоінт
<img src="/imagepreset?src=photo.jpg&preset=thumb" alt="Мініатюра">
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
use Fomvasss\Imagepresets\Facades\Imagepreset;

$url = Imagepreset::url('storage/images/photo.jpg', ['w' => 400, 'h' => 300]);
```

### Blade-директива

```blade
<img src="@imagepreset('storage/images/photo.jpg', ['w' => 600, 'fm' => 'webp'])" alt="Фото">
```

### HTML-приклад

```html
<img src="/imagepreset?src=storage/images/photo.jpg&w=800&fm=webp" alt="Фото">
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
{"message":"imagepreset_request","context":{"params":{"src":"products/photo.jpg","w":640,"fm":"webp"},"ip":"127.0.0.1","url":"http://app.test/imagepreset?src=..."}}
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

Папка кешу `/imagepreset` містить автоматично згенеровані файли, які завжди можна
відновити повторними запитами. Включати їх у бекап — марна трата місця і часу.

### Рекомендовано: окремий диск поза областю бекапу

Визначте окремий filesystem-диск, що знаходиться **за межами** директорії, яка потрапляє в бекап:

```php
// config/filesystems.php
'imagepresets' => [
    'driver' => 'local',
    'root'   => storage_path('app/imagepresets'), // не всередині app/public
    // 'url'    => env('APP_URL').'/imagepresets', // не обовязково, оскільки цей диск не використовується для публічного доступу
    'visibility' => 'public',
    'throw'      => false,
],
```

```ini
# .env
IMAGEPRESET_DISK=imagepresets
```

Тепер папка кешу повністю поза `storage/app/public` і ніколи не потрапить
у бекапи, що включають лише `storage_path('app/public')`.

> **Примітка:** Директорія кешу створюється автоматично при першому запиті до кожного
> пресету — жодних ручних дій після відновлення з бекапу не потрібно.

---

## HTTP-кешування та CDN / Reverse Proxy

Кожна відповідь з ендпоінту `/imagepreset` містить заголовки, оптимізовані для агресивного edge-кешування:

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

    location /imagepreset {
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

#### Перевірка роботи кешу

Зробіть два однакових GET-запити — перший `MISS`, другий має повернути `HIT`.
Команда працює для перевірки і **Nginx** (`X-Cache-Status`), і **Cloudflare** (`cf-cache-status`):

```bash
# Надсилає GET, відкидає тіло, виводить заголовки відповіді
curl -s -o /dev/null -D - "https://example.com/imagepreset?src=photo.jpg&w=800&fm=webp"
```

Очікувані заголовки у відповіді:

```
X-Cache-Status: HIT        # Nginx proxy cache
cf-cache-status: HIT       # Cloudflare edge cache
```

> **Увага:** `curl -I` надсилає **HEAD**-запит — Cloudflare не кешує HEAD і завжди повертає
> `cf-cache-status: DYNAMIC`. Для перевірки кешу завжди використовуйте GET-запит вище.

| `X-Cache-Status` | Що означає |
|---|---|
| `MISS` | Кешу немає — запит пішов у PHP |
| `HIT` | Відповідь з Nginx-кешу, PHP не запускався |
| `EXPIRED` | Кеш є, але протермінований — оновлюється |
| `BYPASS` | Кешування обійдено |

| `cf-cache-status` | Що означає |
|---|---|
| `MISS` | Cloudflare не має кешу — запит пішов на сервер |
| `HIT` | Відповідь з edge-кешу Cloudflare |
| `DYNAMIC` | Не кешується — HEAD-запит або відсутня Cache Rule |
| `EXPIRED` | Кеш протермінований — оновлюється з origin |

Порівняти час відповіді — `HIT` зазвичай у 10–100 разів швидший:

```bash
time curl -s "https://example.com/imagepreset?src=photo.jpg&w=800" -o /dev/null
```

### Cloudflare

Додайте Cache Rule у панелі Cloudflare:

- **If** → URI Path starts with `/imagepreset`
- **Then** → Cache Level: Cache Everything, Edge Cache TTL: 1 year

Або через Terraform / API:

```json
{
  "description": "Cache imagepresets",
  "expression": "(http.request.uri.path starts_with \"/imagepreset\")",
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
     --data '{"prefixes":["https://example.com/imagepreset"]}'
```

> **Порада:** Використовуйте версіоновані шляхи (`photo_v2.jpg`) або додайте query-параметр
> (`?v=2`), щоб скинути кеш без повного purge.

---

## Заголовки відповіді

Кожна відповідь містить:

| Заголовок | Значення |
|---|---|
| `Content-Type` | Коректний MIME-тип |
| `Cache-Control` | Дивіться розділ [HTTP-кешування](#http-кешування) нижче |
| `ETag` | На основі mtime + розміру файлу |
| `Last-Modified` | Час останньої зміни файлу |
| `Content-Disposition` | `inline` |
| `X-Content-Type-Options` | `nosniff` |
| `Content-Security-Policy` | Лише SVG: `default-src 'none'; style-src 'unsafe-inline'; sandbox` |

### HTTP-кешування

Пакет реалізує **інтелектуальні заголовки кешування** в залежності від стану генерування файлу:

**Новогенеровані файли (перший запит):**
```
Cache-Control: no-store
```
- Файл щойно створений — можуть бути проблеми
- Запобігає агресивному кешуванню браузером/CDN
- Наступний запит переперевірить вміст

**Кешовані файли (наступні запити):**
```
Cache-Control: public, max-age=31536000, s-maxage=31536000, immutable
```
- Файл існує та стабільний
- URL містить MD5 хеш всіх параметрів — вміст ніколи не змінюється для того ж URL
- Безпечно кешувати на 1 рік у браузері та CDN (Cloudflare, Fastly, Akamai тощо)
- `immutable` сигналізує, що URL адресується за вмістом — файл ніколи не зміниться

**Конфігурація:**

```php
// config/imagepresets.php
'cache_max_age' => env('IMAGEPRESET_CACHE_MAX_AGE', 31536000),  // 1 рік у секундах
```

Цей підхід мінімізує трафік і навантаження на сервер при збереженні надійності під час фази генерування.

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
| Довільна генерація URL | Опціональний Signed URL (дивіться нижче) |

---

## Signed URL (опціонально)

За замовчуванням ендпоінт відкритий — будь-який запит, що проходить whitelist-валідацію, обробляється.
Увімкніть Signed URL, щоб обмежити доступ **лише** до URL, згенерованих сервером.

### Увімкнення

```ini
# .env
IMAGEPRESET_SIGNED_URL=true
```

Або в `config/imagepresets.php`:

```php
'route' => [
    // ...
    'signed' => true,
],
```

### Як це працює

- `imagepreset_url()`, `Imagepreset::url()` та `@imagepreset()` автоматично генерують
  безстроковий підписаний URL через `URL::signedRoute()`.
- Middleware `signed` перевіряє HMAC-підпис при кожному запиті.
- Запити без дійсного підпису повертають **403 Forbidden**.
- Підміна будь-якого параметра (наприклад, `w=300` → `w=9999`) робить підпис недійсним.

### Приклад

```php
// Генерує: https://example.com/imagepreset?fm=webp&signature=...&src=photo.jpg&w=800
$url = imagepreset_url('photo.jpg', ['w' => 800, 'fm' => 'webp']);
```

```blade
<img src="@imagepreset('photo.jpg', ['w' => 600, 'fm' => 'webp'])" alt="Photo">
```

### Примітки

- Підписані URL є **безстроковими** (без expiry). Це робить їх повністю сумісними з
  CDN / reverse-proxy кешуванням — додаткового налаштування CDN не потрібно.
- При `signed = false` (за замовчуванням) поведінка ідентична попереднім версіям — **повна зворотна сумісність**.
- Підпис базується на `APP_KEY`. Ротація ключа анулює всі раніше згенеровані URL.

---

## Trusted Bypass

У мультидоменних або змішаних фронтенд-системах може знадобитися генерувати URL зображень
з довільними розмірами з Blade-шаблонів або бекенд-коду — розмірами, яких немає в `allowed_*`
конфігурації та які відрізняються для кожної сторінки чи домену.

**Trusted bypass** дозволяє URL, згенерованим на бекенді, обходити перевірки allowlist через
підписаний сервером токен (`_t`). Запити без токена — включно з усіма публічними API-запитами —
продовжують проходити повну валідацію без змін.

### Увімкнення

```ini
# .env (лише для Blade/бекенд сайту — залишайте false на публічних API-сайтах)
IMAGEPRESET_TRUSTED_BYPASS=true
```

Або в `config/imagepresets.php`:

```php
'trusted_bypass' => true,
```

### Використання

Передайте `true` третім аргументом у helper, Facade або Blade-директиву:

```php
// Helper
$url = imagepreset_url('photo.jpg', ['w' => 756, 'h' => 380, 'fm' => 'webp'], true);

// Facade
Imagepreset::url('photo.jpg', ['w' => 756, 'h' => 380], true);
```

```blade
{{-- Blade-директива --}}
<img src="@imagepreset('photo.jpg', ['w' => 756, 'h' => 380, 'fm' => 'webp'], true)" alt="Фото">
```

Згенерований URL містить 16-символьний HMAC-SHA256 токен (`_t`), підписаний `APP_KEY`:

```
/imagepreset?fm=webp&h=380&src=photo.jpg&w=756&_t=a3f9c2e1b7d04518
```

### Що обходиться / що ні

| Перевірка | Обходиться з `_t` |
|---|---|
| `allowed_widths` | Так |
| `allowed_heights` | Так |
| `allowed_sizes` | Так |
| `allowed_qualities` | Так |
| `allowed_fits` | Так |
| `allowed_formats` | Так |
| `w` / `h` max:20000 | **Ні** — завжди перевіряється |
| `fit` потребує розмірів | **Ні** — завжди перевіряється |
| Path traversal у `src` | **Ні** — завжди перевіряється |
| Allowlist remote-хостів | **Ні** — завжди перевіряється |
| Максимальна площа пікселів (`max_image_pixels`) | **Ні** — завжди перевіряється |

### Безпека

- Токен — HMAC-SHA256, підписаний `APP_KEY`. Підробити без ключа неможливо.
- Зміна будь-якого параметра (напр. `w=756` → `w=9999`) анулює токен.
- Токен включається до підпису маршруту Laravel, якщо увімкнено `route.signed = true` — подвійний захист.
- Параметр `_t` **виключається з cache key** — trusted і звичайний запити з однаковими логічними параметрами використовують один кеш-файл.
- Залишайте `trusted_bypass = false` (за замовчуванням) на сайтах, де всі запити зображень приходять з публічного інтернету.
- Токен підписаний `APP_KEY`. Ротація ключа анулює всі раніше згенеровані trusted URL — така сама поведінка, як і при `signed=true`.

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

