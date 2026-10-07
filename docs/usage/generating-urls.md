# Generating URLs

An image is requested from the package endpoint with the source and the transformation in the query string:

```text
GET /imagepreset?src=images/photo.jpg&w=600&fm=webp
```

The URL can be written by hand (in HTML, in a frontend app) or generated in PHP. All three PHP entry points call the same method, `ImagepresetService::url()`.

## Helper

```php
imagepreset_url(string $src, array|string $params = [], bool $bypass = false): string
```

```php
imagepreset_url('images/photo.jpg', ['w' => 600, 'fm' => 'webp']);
// http://example.com/imagepreset?fm=webp&src=images%2Fphoto.jpg&w=600

imagepreset_url('images/photo.jpg', 'thumb');            // named preset
imagepreset_url('images/photo.jpg', ['preset' => 'thumb', 'fm' => 'jpg']);

imagepreset_url('https://cdn.example.com/a/b.jpg', ['w' => 300]);
// http://example.com/imagepreset?src=https%3A%2F%2Fcdn.example.com%2Fa%2Fb.jpg&w=300
```

- A string as the second argument is a preset name — the same as `['preset' => $name]`.
- The URL is absolute, built with `route()` (or `URL::signedRoute()` when [signed URLs](signed-urls.md) are on).
- Parameters are sorted by key and cast to strings, so the same input always gives the same URL.
- `$bypass = true` adds a trusted token when `trusted_bypass` is enabled, see [Trusted bypass](trusted-bypass.md).
- With `backend_url_enabled = false` the helper returns `$src` unchanged.

The helper doesn't validate anything: a URL for a size outside the allowlists is generated fine and returns 404 when requested.

## Facade

```php
use Fomvasss\Imagepresets\Facades\Imagepreset;

Imagepreset::url('images/photo.jpg', ['w' => 400, 'h' => 300]);
Imagepreset::url('images/photo.jpg', 'avatar');
```

The facade resolves the `imagepresets` container binding (`ImagepresetService`). The `Imagepreset` alias is registered automatically.

## Blade directive

```blade
<img src="@imagepreset('images/photo.jpg', ['w' => 600, 'fm' => 'webp'])" alt="">
<img src="@imagepreset($post->image, 'thumb')" alt="">
<img src="@imagepreset($post->image, ['w' => 756, 'h' => 380], true)" alt="">
```

`@imagepreset(...)` compiles to `<?php echo imagepreset_url(...); ?>` — the arguments are the helper's arguments, and the output is not escaped.

## Responsive images

```blade
<img
    src="@imagepreset($src, ['w' => 600])"
    srcset="@imagepreset($src, ['w' => 400]) 400w,
            @imagepreset($src, ['w' => 800]) 800w,
            @imagepreset($src, ['w' => 1200]) 1200w"
    sizes="(max-width: 600px) 100vw, 600px"
    alt=""
>
```

Every width in `srcset` must be in `allowed_widths` (or use [trusted bypass](trusted-bypass.md)).

## Writing URLs by hand

The endpoint doesn't care how the URL was produced. Parameter order doesn't matter: the cache key is built from the sorted query string, so `?w=600&src=a.jpg` and `?src=a.jpg&w=600` share one cached file.

```html
<img src="/imagepreset?src=images/photo.jpg&preset=thumb" alt="">
```

> [!NOTE]
> Query parameters the package doesn't know, like `v=2`, are not part of the cache key (since 1.19.5): they don't produce a separate cached file and don't bust the cache. To change an image, give its source a new file name — see [HTTP caching & CDN](http-caching.md).
