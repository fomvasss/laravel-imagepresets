# Named presets

A preset is a named set of transformation parameters in `config/imagepresets.php`:

```php
'presets' => [
    'thumb' => ['w' => 300, 'h' => 200, 'fit' => 'crop', 'fm' => 'webp', 'q' => 80],
    'hero' => ['w' => 1200, 'fm' => 'webp', 'q' => 85],
    'avatar' => ['w' => 96, 'h' => 96, 'fit' => 'crop', 'fm' => 'webp'],

    // og:image — 1200×630, JPG is the safest for social parsers
    'og_image' => ['w' => 1200, 'h' => 630, 'fit' => 'crop', 'fm' => 'jpg', 'q' => 85, 'bg' => 'ffffff'],

    // Google Merchant Center — whole product visible on a white square
    'merchant' => ['w' => 800, 'h' => 800, 'fit' => 'contain', 'fm' => 'jpg', 'q' => 88, 'bg' => 'ffffff'],
],
```

Keys a preset can set: `w`, `h`, `q`, `fm`, `fit`, `blur`, `sharp`, `or`, `crop`, `bg`.

## Using a preset

```php
imagepreset_url('photo.jpg', 'thumb');
imagepreset_url('photo.jpg', ['preset' => 'thumb']);
Imagepreset::url('photo.jpg', 'thumb');
```

```blade
<img src="@imagepreset('photo.jpg', 'thumb')" alt="">
```

```html
<img src="/imagepreset?src=photo.jpg&preset=thumb" alt="">
```

An unknown preset name returns 404.

## Allowlists don't apply to preset values

Preset values come from your config, so they are not checked against `allowed_widths`, `allowed_heights`, `allowed_sizes`, `allowed_qualities`, `allowed_fits` and `allowed_formats`. The `avatar` preset above works even though 96 is not in `allowed_widths`.

## Overriding preset values

Parameters passed next to the preset override it:

```php
imagepreset_url('photo.jpg', ['preset' => 'thumb', 'fm' => 'jpg']);
```

Overrides come from the request, so they **are** validated: `?preset=thumb&w=301` returns 404 unless 301 is allowed. One more rule follows from the validation order:

- An override replaces the preset value, it doesn't combine with it: `?preset=thumb&w=600` gives `w=600`, `h=200` — and `w` alone is checked against `allowed_widths`, not as a pair.

> [!WARNING]
> The cache key is the query string (`preset=thumb&src=…`), not the resolved parameters. After changing a preset definition run `php artisan imagepresets:clear` and purge your CDN, otherwise the old images stay.
