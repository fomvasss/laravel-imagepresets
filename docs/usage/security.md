# Security

The endpoint is public and does CPU- and disk-heavy work on request. This page collects what the package protects against and what is left to you.

## Built-in protection

| Threat | Protection |
|---|---|
| Disk filling with variants | Allowlists for sizes, qualities, fits, formats ([Allowlists](allowlists.md)); `w`/`h` ≤ 20000 |
| Request floods | `throttle:2400,1` on the route |
| Path traversal | `src` with `..`, a null byte or a leading `.` is rejected |
| SSRF | Remote hosts from `allowed_hosts` / `APP_URL` only; literal private/reserved IPs and `localhost` rejected; redirects not followed |
| Oversized downloads | `max_download_bytes` |
| Image bombs | `max_image_pixels` on the source |
| SVG XSS | Sanitization; `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox` on SVG responses |
| Content sniffing | `X-Content-Type-Options: nosniff` |
| Cache stampede | `Cache::lock()` per file, double-checked after acquiring |
| Broken files cached forever | Atomic write with integrity check; optional `verify_decode` |
| Arbitrary URLs | Optional [signed URLs](signed-urls.md) |

Requests that fail validation return 404, not 422 — the endpoint doesn't reveal which check failed.

## Things to check in your app

### Private images under `storage/`

Local `src` is looked up on the `public` disk, then **anywhere under `storage/`**, then under `public/`. An image in `storage/app/private` can be fetched with `src=app/private/…`. See [Image sources](sources.md#local-files).

### Extra query parameters

The cache file name is built from the whole query string. Parameters the package doesn't know are ignored by validation but still change the file name, so `?src=a.jpg&w=300&x=1`, `&x=2`, `&x=3`… each generate and store a new copy of an allowed size. The allowlists limit *sizes*, not the number of files.

If that matters for your site, use [signed URLs](signed-urls.md) (extra parameters break the signature) or a CDN/WAF rule that strips or rejects unknown parameters, and keep the throttle.

### Wildcards in production

`['*']` in the allowlists, together with the point above, lets anyone generate unlimited files of any size up to 20000 px. Use wildcards only behind signed URLs or in non-public environments.

### Remote hosts

The SSRF check doesn't resolve DNS. A host in `allowed_hosts` that points to an internal address will be fetched. List only hosts you trust.

### Trusted bypass on public APIs

Keep `trusted_bypass` off where clients build URLs themselves. It is safe only as long as `APP_KEY` is secret.

### Lock store

On several app servers the cache store must be shared (Redis, Memcached, database), otherwise concurrent first requests generate the same image on every server. See [Configuration](../configuration.md#cache-lock).
