# Cache maintenance

Generated files stay on the disk forever; nothing expires them. Remove them when sources or presets change, and check them after crashes.

## Clearing the cache

```bash
php artisan imagepresets:clear
php artisan imagepresets:clear --temp
php artisan imagepresets:clear --disk=s3 --path=imagepresets
```

Deletes the `path` directory on the `disk` (both default to the config values). `--temp` also empties `source_dir` and `temp_dir` — downloaded remote sources and Glide leftovers.

> [!WARNING]
> With an empty `path` (the default) the command exits with an error: the cache is mixed with the rest of the disk and cannot be told apart. Set `IMAGEPRESET_PATH` to a dedicated subdirectory, or pass `--path=`. Before 1.19.1 it called `deleteDirectory('')` and **deleted everything on the disk** — on `public` all uploaded files too.

Files are regenerated on the next request. Purge the CDN / nginx cache as well — see [HTTP caching](http-caching.md#invalidation).

## Verifying cached files

A worker killed during encoding (OOM, deploy restart) can leave a broken image that would then be served with year-long cache headers. Since 1.17 the package writes to a temporary file, checks it and renames it atomically, so this should no longer happen for new files. `imagepresets:verify` finds what is already broken:

```bash
php artisan imagepresets:verify                 # list only
php artisan imagepresets:verify --delete        # delete corrupted files and old *.tmp* leftovers
php artisan imagepresets:verify --deep          # also decode every webp
php artisan imagepresets:verify --deep --gray-threshold=40
php artisan imagepresets:verify --delete-suspects
```

What it reports:

- **Corrupted** — truncated files: JPEG without the end marker, PNG without `IEND`, GIF without the trailer, WebP whose RIFF size doesn't match the file size; for AVIF, a file `getimagesize()` can't read.
- **Orphaned tmp** — `*.tmp*` files older than one hour, left by processes killed before the rename.
- **Suspect webp** (`--deep`) — WebP files that decode, but where more than `--gray-threshold` percent (default 25) of a 12×12 grid of sampled pixels is exactly `rgb(128,128,128)`. That is what libwebp paints where the payload is damaged although the container size is right.

Suspects are a heuristic: an image with large flat gray areas is a false positive. `--delete` never touches them; review the list and use `--delete-suspects` (it implies `--deep`).

Deleted files are regenerated on the next request — purge them from the CDN too.

> [!WARNING]
> - The command reads files through local paths: **local disks only.** On a remote disk it exits with an error (before 1.19.1 every file on S3/GCS was reported as corrupted, and `--delete` removed them all).
> - With an empty `path` it exits with an error instead of scanning the whole disk, uploaded originals included.
> - The deep check needs GD with WebP support; without it no suspects are reported.

## Verify decode at generation time

```env
IMAGEPRESET_VERIFY_DECODE=true
```

Runs the same gray-filler check on every newly generated WebP (threshold 25 %) before it reaches the cache. A rejected image is not stored, the request returns 404 and the next request tries again. Costs a few milliseconds per generation, once per file. Same false-positive caveat as above — that is why it's off by default.

## Temporary directories

| Directory | Contents | Cleaned |
|---|---|---|
| `source_dir` | Working copy of the source during generation; downloaded remote sources (`dl_*`) | Working copies after each generation; `dl_*` only when a new image is generated — see [Image sources](sources.md#remote-sources-are-downloaded-on-every-request) |
| `temp_dir` | Glide's temporary files | By Glide |
| `local_cache_dir` | Results waiting for upload to a remote disk | After a successful upload |

`imagepresets:clear --temp` empties `source_dir` and `temp_dir`. Don't run it while images are being generated — it can remove a working copy mid-generation (that request returns 404).
