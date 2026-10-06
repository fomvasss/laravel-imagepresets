# Query parameters

Parameters of `GET /imagepreset`. Any failed rule returns 404.

| Parameter | Rule | Allowlist / limit | Description |
|---|---|---|---|
| `src` | required string, ≤ 1000 chars | Local: no `..`, null byte or leading `.`. Remote: `allowed_hosts` or `APP_URL` host | Source image, see [Image sources](../usage/sources.md) |
| `preset` | string | Key of `presets` | [Named preset](../usage/presets.md); its values are not checked against allowlists |
| `w` | integer 1–20000 | `allowed_widths` (alone) or `allowed_sizes` (with `h`) | Width, px |
| `h` | integer 1–20000 | `allowed_heights` (alone) or `allowed_sizes` (with `w`) | Height, px |
| `q` | integer | `allowed_qualities` | Quality. Default `quality` |
| `fm` | string | `allowed_formats` | Output format. Default `format` |
| `fit` | string, needs `w` or `h` in the request | `allowed_fits` | [Fit method](../usage/transformations.md#fit-methods). Default `default_fit_both` / `default_fit_one` |
| `blur` | integer 0–`blur_max` | | Blur |
| `sharp` | integer 0–`sharp_max` | | Sharpen |
| `or` | string | `allowed_orientations` | `auto` (EXIF), `0`, `90`, `180`, `270` |
| `crop` | `^\d+,\d+,\d+,\d+$` | | Rectangle `width,height,x,y` cut before resizing |
| `bg` | `^[0-9a-fA-F]{3,8}$` | | Background colour, hex without `#` |
| `_t` | string, exactly 16 chars | | Trusted token, see [Trusted bypass](../usage/trusted-bypass.md) |
| `signature` | | | Added by signed URLs, checked by the `signed` middleware |

- With a valid `_t`, the `allowed_sizes`/`widths`/`heights`/`qualities`/`fits`/`formats` checks are skipped.
- `['*']` disables `allowed_sizes`, `allowed_widths`, `allowed_heights`, `allowed_qualities`.
- Empty values (`?w=`) count as absent.
- Unknown parameters are ignored by processing but are part of the cache key (`_t` is not).
