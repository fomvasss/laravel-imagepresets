# Trusted bypass

Some pages need sizes that are not in the allowlists — a Blade layout with dozens of card sizes, several domains with different designs. Trusted bypass lets URLs generated on the server skip the allowlists, while requests built by anyone else are still validated.

```env
IMAGEPRESET_TRUSTED_BYPASS=true
```

Pass `true` as the third argument:

```php
imagepreset_url('photo.jpg', ['w' => 756, 'h' => 380, 'fm' => 'webp'], true);
Imagepreset::url('photo.jpg', ['w' => 756, 'h' => 380], true);
```

```blade
<img src="@imagepreset('photo.jpg', ['w' => 756, 'h' => 380, 'fm' => 'webp'], true)" alt="">
```

The URL gets a `_t` parameter — the first 16 hex characters of an HMAC-SHA256 of the other parameters, keyed with `APP_KEY`:

```text
https://example.com/imagepreset?_t=625ecf4b5d9bfc22&fm=webp&h=380&src=photo.jpg&w=756
```

With `trusted_bypass = false` the third argument is ignored and no token is added.

## What the token skips

| Check | Skipped with a valid `_t` |
|---|---|
| `allowed_sizes`, `allowed_widths`, `allowed_heights` | yes |
| `allowed_qualities` | yes |
| `allowed_fits` | yes |
| `allowed_formats` | yes |
| `allowed_orientations`, `blur_max`, `sharp_max`, `crop`/`bg` format | no |
| `w`/`h` between 1 and 20000 | no |
| `fit` requires `w` or `h` | no |
| `src`: path traversal, remote host allowlist, SSRF checks | no |
| `max_image_pixels` | no |

A wrong or tampered token is not an error by itself — the request is validated like any other and passes only if it fits the allowlists.

## Notes

- Changing, adding or removing any parameter invalidates the token — it is computed over the whole query string except `_t`.
- `_t` is excluded from the cache key, so a trusted and a plain request for the same parameters share one file.
- Rotating `APP_KEY` invalidates all tokens; affected URLs then fall back to normal validation.
- Keep it off on sites where image URLs are built by clients (public APIs, SPAs): the bypass is only useful for URLs your server renders.
- `_t` must be exactly 16 characters; any other length is a validation error (404).

> [!WARNING]
> Trusted bypass doesn't work together with [signed URLs](signed-urls.md#things-to-watch): with `route.signed = true` the token never validates.
