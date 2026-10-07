# Image sources

`src` is either a local path or an `http(s)://` URL, up to 1000 characters.

## Local files

A relative path is looked up in this order, first match wins:

1. the `public` disk — `Storage::disk('public')`, usually `storage/app/public`
2. `storage_path($src)` — only for `src` starting with `app/public/`
3. `public_path($src)` — anywhere under `public/`

```text
src=products/photo.jpg          # storage/app/public/products/photo.jpg
src=images/logo.png             # public/images/logo.png
```

A leading slash is ignored, backslashes and `//` are normalised. A `src` containing `..` or a null byte, or starting with `.`, is rejected with 404.

The lookup always uses the `public` disk, regardless of the `disk` option (which is where the *results* are stored).

Files elsewhere under `storage/` (`app/private`, `logs`, …) are not served. Before 1.19.2 step 2 covered the whole of `storage/`, so `src=app/private/invoices/scan.jpg` returned a private upload.

## Remote URLs

```php
imagepreset_url('https://cdn.example.com/photos/1.jpg', ['w' => 400]);
```

The host must be in `allowed_hosts` (exact, case-insensitive match — no wildcards, subdomains are listed separately) or be the host of `APP_URL`:

```php
'allowed_hosts' => [
    'cdn.example.com',
    'images.example.org',
],
```

The URL is normalised first: scheme and host lowercased, an IDN host converted to ASCII, percent-encoding of the path and query made canonical.

Checks:

- only `http` and `https`;
- a literal IP in a private or reserved range, and `localhost`, are rejected;
- redirects are not followed — a `3xx` answer counts as a failure;
- non-`2xx` responses, timeouts (30 s) and connection errors give 404;
- the body may not exceed `max_download_bytes` (20 MB), checked against `Content-Length` and the actual size;
- the image area may not exceed `max_image_pixels`.

> [!NOTE]
> The SSRF check looks at literal IPs only. A hostname that *resolves* to a private address is not detected, so `allowed_hosts` is the real protection — list only hosts you control or trust.

### Same-origin URLs

A URL on the `APP_URL` host whose path starts with `/storage/` is read from disk instead of downloaded: `https://example.com/storage/products/1.jpg` is resolved like `src=products/1.jpg`. Other paths on your own host are downloaded over HTTP like any remote URL.

### Remote sources are downloaded on every request

The source is resolved before the cache is checked, so a request for a remote `src` downloads the image again even when the result is already cached. The downloaded copy (`source_dir/dl_*`) is deleted only when a new image is generated — on cache hits it stays behind.

Consequences:

- every uncached-at-CDN request for a remote image costs an outgoing HTTP request;
- `storage/app/imagepreset_sources` grows; clean it periodically with `php artisan imagepresets:clear --temp`, or with a scheduled job that deletes old `dl_*` files.

A CDN or reverse-proxy cache in front of the endpoint ([HTTP caching](http-caching.md)) keeps these requests rare. For images you control, prefer local paths.

## Image-bomb protection

`max_image_pixels` (default 150 000 000) limits width × height of the source, read from the file header with `getimagesize()`. It applies to local and remote raster images. Formats `getimagesize()` can't read (HEIC, for example) are not checked. `0` disables the check.

## Missing or broken sources

A source that doesn't exist, can't be downloaded or can't be decoded returns 404. Decoding errors are logged (`[Imagepresets] makeImage failed`) only when `app.debug` is on.
