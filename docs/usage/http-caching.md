# HTTP caching & CDN

Without a cache in front, every image request boots Laravel: validation, a disk check and sending the file through PHP. Put a CDN or a reverse-proxy cache in front of the endpoint.

## Response headers

| Header | Value |
|---|---|
| `Cache-Control` | `public, max-age=<cache_max_age>, s-maxage=<cache_max_age>, immutable` — or `no-store` on the request that generated the file |
| `ETag` | From file mtime and size |
| `Last-Modified` | File mtime |
| `Content-Type` | By output format |
| `Content-Disposition` | `inline` |
| `X-Content-Type-Options` | `nosniff` |
| `Content-Security-Policy` | SVG only: `default-src 'none'; style-src 'unsafe-inline'; sandbox` |

`cache_max_age` defaults to one year (`IMAGEPRESET_CACHE_MAX_AGE=31536000`).

### The first response is `no-store`

The request that generates a raster image answers with `Cache-Control: no-store`; every later request for it gets the long-lived header. A freshly generated file is therefore never stored by the browser or a CDN on the first hit — the second request is the one that gets cached. SVG is the exception: it always gets the long-lived header.

## Nginx with PHP-FPM

`fastcgi_cache` stores responses on the nginx host, so cached images don't reach PHP at all:

```nginx
fastcgi_cache_path /var/cache/nginx/imagepresets
    levels=1:2
    keys_zone=imagepresets:20m
    max_size=2g
    inactive=365d
    use_temp_path=off;

server {
    # ...

    location = /imagepreset {
        fastcgi_cache imagepresets;
        fastcgi_cache_key "$scheme$host$request_uri";
        fastcgi_cache_valid 200 365d;
        fastcgi_cache_use_stale error timeout updating http_500 http_503;
        fastcgi_cache_lock on;
        add_header X-Cache-Status $upstream_cache_status always;

        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # same as in your `location ~ \.php$`
    }
}
```

`location = /imagepreset` must match `route.prefix`. Nginx honours `Cache-Control` from the response, so the `no-store` first response is not cached and the next one is.

If the app is behind an HTTP upstream instead of PHP-FPM (Octane, another proxy), use the same directives with the `proxy_` prefix (`proxy_cache_path`, `proxy_cache`, `proxy_pass`).

## Cloudflare

Cloudflare doesn't cache a URL without a file extension by default. Add a Cache Rule:

- **If** URI Path starts with `/imagepreset`
- **Then** Eligible for cache; Edge TTL — use cache-control header if present (or override with 1 year)

```json
{
  "description": "Cache imagepresets",
  "expression": "(starts_with(http.request.uri.path, \"/imagepreset\"))",
  "action": "set_cache_settings",
  "action_parameters": {
    "cache": true,
    "edge_ttl": { "mode": "respect_origin" }
  }
}
```

The query string is part of Cloudflare's default cache key, so every size is cached separately.

## Throttling behind a proxy

The route has `throttle:2400,1` — 2400 requests per minute per client IP. Behind a CDN or load balancer every request comes from a handful of proxy IPs unless Laravel's trusted proxies are configured, and a busy page can hit the limit (429). Configure `TrustProxies` for your CDN, or raise the limit:

```env
IMAGEPRESET_THROTTLE=throttle:10000,1
```

## Checking the cache

Request the same URL twice with GET (`curl -I` sends HEAD, which Cloudflare doesn't cache):

```bash
curl -s -o /dev/null -D - "https://example.com/imagepreset?src=photo.jpg&w=800"
```

| Header | `MISS` | `HIT` |
|---|---|---|
| `X-Cache-Status` (nginx) | went to PHP | served by nginx |
| `cf-cache-status` (Cloudflare) | went to origin | served from the edge |

Cloudflare's `DYNAMIC` means the response is not eligible for cache — no Cache Rule, or a HEAD request.

## Invalidation

A changed source file, preset or default doesn't change the URL, so the old image stays in every cache layer. Clear all of them:

```bash
php artisan imagepresets:clear                        # generated files on the disk
find /var/cache/nginx/imagepresets -type f -delete    # nginx

curl -X POST "https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/purge_cache" \
     -H "Authorization: Bearer {TOKEN}" \
     -H "Content-Type: application/json" \
     --data '{"prefixes":["example.com/imagepreset"]}'
```

To change one image without purging, give its source a new file name — the URL changes with it. Since 1.19.5 an extra query parameter (`&v=2`) no longer makes a new cache file: unknown parameters are left out of the cache key.
