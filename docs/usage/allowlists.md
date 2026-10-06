# Allowlists & audit log

The endpoint is public. Without limits anyone could request `w=1..20000` for every image and fill the disk with variants. The allowlists restrict which values a request may use; anything else returns 404.

## What is checked

| Request has | Checked against |
|---|---|
| `w` and `h` | `allowed_sizes` — the exact `[w, h]` pair |
| `w` only | `allowed_widths` |
| `h` only | `allowed_heights` |
| `q` | `allowed_qualities` |
| `fit` | `allowed_fits` |
| `fm` | `allowed_formats` |
| `or` | `allowed_orientations` |
| `blur`, `sharp` | `0`–`blur_max`, `0`–`sharp_max` |

When both `w` and `h` are given, `allowed_widths`/`allowed_heights` are not consulted — only the pair.

```php
'allowed_sizes' => [[300, 200], [600, 400], [1200, 800]],
'allowed_widths' => [100, 200, 300, 400, 600, 800, 1000, 1200, 1600],
'allowed_heights' => [100, 200, 300, 400, 600, 800],
'allowed_qualities' => [50, 60, 70, 80, 90, 100],
'allowed_fits' => ['contain', 'crop', 'fill', 'fill-max', 'max', 'stretch'],
'allowed_formats' => ['webp', 'jpg', 'png', 'gif'],
```

Values coming from a [named preset](presets.md) are not checked. Requests with a valid [trusted token](trusted-bypass.md) skip the size, quality, fit and format lists.

## Wildcard

`['*']` removes the restriction for `allowed_sizes`, `allowed_widths`, `allowed_heights` and `allowed_qualities`:

```php
'allowed_widths' => ['*'],
'allowed_heights' => ['*'],
'allowed_sizes' => ['*'],
'allowed_qualities' => ['*'],
```

What still applies: `w` and `h` are integers from 1 to 20000; `q` is an integer. With the quality wildcard any integer passes, including `0` or `500` — Glide replaces values outside 0–100 with its own default (85), but each value is still a separate cached file.

`allowed_fits`, `allowed_formats` and `allowed_orientations` have no wildcard — list the values.

> [!WARNING]
> Wildcards on a public site let anyone generate an unlimited number of variants. Use them in development to find the sizes you need (below), or protect the endpoint with [signed URLs](signed-urls.md).

## Audit log

The audit log shows which parameters the frontend actually requests. Typical workflow:

1. In local or staging, set the size and quality allowlists to `['*']` and enable the log:

   ```env
   IMAGEPRESET_AUDIT_LOG=true
   # IMAGEPRESET_AUDIT_LOG_ONLY_NEW=true   # default: only images not cached yet
   ```

2. Click through the site. Each valid request is written to the default log channel (`LOG_CHANNEL`) as an `info` entry:

   ```text
   local.INFO: imagepreset_request {"params":{"src":"products/photo.jpg","w":"640","fm":"webp"},"ip":"127.0.0.1","url":"http://app.test/imagepreset?fm=webp&src=products%2Fphoto.jpg&w=640"}
   ```

   `params` are the parameters after a [preset](presets.md) is merged in (the `preset` name itself is dropped). Values from the query string are logged as strings (`"w":"640"`), values from a preset as numbers (`"w":300`).

3. Collect the unique values:

   ```bash
   grep -oh '"w":"\?[0-9]*' storage/logs/*.log | sort -u
   grep -oh '"h":"\?[0-9]*' storage/logs/*.log | sort -u
   grep -oh '"q":"\?[0-9]*' storage/logs/*.log | sort -u
   ```

4. Put them into the allowlists (or presets) and turn the log off in production.

With `only_new = true` a request is logged only when its file is not on the disk yet, so each combination appears once (until the cache is cleared). Requests rejected by validation are never logged.
