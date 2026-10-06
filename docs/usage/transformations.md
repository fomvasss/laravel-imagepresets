# Transformations

Transformations are Glide parameters passed in the query string. Only the parameters below reach Glide; anything else is ignored (but still becomes part of the cache key). The full table with validation rules — [Query parameters](../reference/query-parameters.md).

## Size and fit

```text
/imagepreset?src=photo.jpg&w=600               # width only
/imagepreset?src=photo.jpg&h=400               # height only
/imagepreset?src=photo.jpg&w=600&h=400&fit=crop
```

- `w` only — must be in `allowed_widths`; `h` only — in `allowed_heights`; both — the pair must be in `allowed_sizes`. See [Allowlists](allowlists.md).
- Without `fit` the package uses `default_fit_both` (`fill`) when both sizes are given and `default_fit_one` (`max`) when one is.
- `fit` without `w` or `h` in the request is rejected (404), even when a preset supplies the size.
- Without `w` and `h` the image keeps its size and is only re-encoded.

### Fit methods

| `fit` | Result |
|---|---|
| `contain` | Scaled to fit inside `w`×`h`, aspect ratio kept, **may upscale**. Output can be smaller than the box on one side |
| `max` | Like `contain`, but **never upscales** |
| `fill` | Like `max` (never upscales), then padded to exactly `w`×`h` |
| `fill-max` | Like `contain` (**upscales** if needed), then padded to exactly `w`×`h` |
| `crop` | Scaled to cover `w`×`h` and cropped from the centre — exactly `w`×`h`, nothing padded |
| `stretch` | Resized to exactly `w`×`h`, aspect ratio ignored |

Padding is transparent; set the colour with `bg` (e.g. `bg=ffffff` for JPG output). Use `fill-max` when the whole image must be visible at an exact size (og:image, product feeds), `crop` when trimming the edges is acceptable.

Glide also understands crop positions (`crop-top`, `crop-bottom-left`, …) and focal points (`crop-25-75`). They work once added to `allowed_fits`.

## Quality and format

```text
/imagepreset?src=photo.jpg&w=600&fm=jpg&q=90
```

- `q` — encoder quality, must be in `allowed_qualities`. Default — `quality` (80). Ignored for `png` and `gif`.
- `fm` — output format, must be in `allowed_formats`. Default — `format` (`webp`). `pjpg` (progressive JPEG) is stored with the `.jpg` extension. Format support depends on the driver, see [Drivers & formats](drivers-and-formats.md).

## Effects

| Parameter | Example | Description |
|---|---|---|
| `blur` | `blur=10` | Blur, `0`–`blur_max` (Glide itself accepts up to 100) |
| `sharp` | `sharp=15` | Sharpen, `0`–`sharp_max` (Glide itself accepts up to 100) |
| `or` | `or=auto` | Orientation: `auto` reads EXIF (photos from phones), or `0`/`90`/`180`/`270` |
| `crop` | `crop=200,200,10,10` | Cut a `width,height,x,y` rectangle from the source before resizing |
| `bg` | `bg=ffffff` | Background colour, 3–8 hex digits without `#`. Fills transparent areas and fit padding |

These parameters are not limited by the size allowlists and work with or without `w`/`h`.

## Converting transparent images to JPG

```php
imagepreset_url('logo.png', ['w' => 400, 'fm' => 'jpg', 'bg' => 'ffffff']);
```

Without `bg` the transparent areas get the driver's default colour.

> [!WARNING]
> The cached file is keyed by the request's query string only. Changing a preset definition, `quality`, `format` or `default_fit_*` in config does not change existing URLs, so the old files keep being served. Run [`imagepresets:clear`](cache-maintenance.md) after such changes and purge the CDN.
