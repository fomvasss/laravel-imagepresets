# Helper, facade & Blade

All three generate the same URL through `Fomvasss\Imagepresets\Services\ImagepresetService::url()`. Usage — [Generating URLs](../usage/generating-urls.md).

| Entry point | Signature |
|---|---|
| Helper | `imagepreset_url(string $src, array\|string $params = [], bool $bypass = false): string` |
| Facade | `Fomvasss\Imagepresets\Facades\Imagepreset::url(string $src, array\|string $params = [], bool $bypass = false): string` |
| Blade | `@imagepreset($src, $params = [], $bypass = false)` |
| Service | `app(ImagepresetService::class)->url(...)`, also bound as `app('imagepresets')` |

## Arguments

| Argument | Description |
|---|---|
| `$src` | Local path or `http(s)://` URL, see [Image sources](../usage/sources.md) |
| `$params` | Array of [query parameters](query-parameters.md), or a string — a preset name, same as `['preset' => $name]` |
| `$bypass` | Add a trusted token (`_t`) when `trusted_bypass` is on, see [Trusted bypass](../usage/trusted-bypass.md) |

## Return value

| Condition | Result |
|---|---|
| `backend_url_enabled = false` | `$src` unchanged |
| `route.signed = true` | `URL::signedRoute(<route.name>, $params)` — absolute, permanent, with `signature` |
| otherwise | `route(<route.name>, $params)` — absolute URL |

Parameters are merged with `src`, sorted by key and cast to strings before the URL is built. Nothing is validated at this point.

## Blade directive

`@imagepreset(...)` compiles to `<?php echo imagepreset_url(...); ?>`. The output is not HTML-escaped.

## Route

| | |
|---|---|
| Method | `GET` |
| URI | `/{route.prefix}`, default `/imagepreset` |
| Name | `route.name`, default `imagepreset` |
| Controller | `Fomvasss\Imagepresets\Http\Controllers\ImagepresetController` (invokable) |
| Middleware | `route.middleware` (default `throttle:2400,1`), plus `signed` when `route.signed` is on |

The route can be used directly: `route('imagepreset', ['src' => 'photo.jpg', 'w' => 300])`.

## Service container

| Binding | Lifetime |
|---|---|
| `ImagepresetService` (alias `imagepresets`) | singleton |
| `ImagepresetValidator`, `SourceResolver`, `GlideProcessor`, `SvgProcessor`, `ResponseBuilder`, `RemoteUrlNormalizer` | singletons |

All classes are `final`; there are no events or extension hooks.
