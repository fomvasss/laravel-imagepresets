# Storage disks

Generated images are stored on the disk named in `disk` (default `public`), inside `path`. The file name is the MD5 of the request's query string plus the output extension:

```text
storage/app/public/imagepresets/9787692cc5c74ee279c1ba4cf3b1aa1f.webp
```

```env
IMAGEPRESET_DISK=public
IMAGEPRESET_PATH=imagepresets
```

## Local disks

A disk whose `driver` is `local`. Glide writes straight into the disk's `root`, and the file is served by PHP with `response()->file()`.

The cache root is taken from `config('filesystems.disks.<disk>.root')`, not from the disk instance — a disk built or re-rooted at runtime (for example `Storage::fake()` in tests) is not followed.

The files are served through the endpoint, so the disk doesn't need a public URL.

### Keeping the cache out of backups

The cache can always be regenerated. To keep it out of backups of `storage/app/public`, give it its own disk:

```php
// config/filesystems.php
'imagepresets' => [
    'driver' => 'local',
    'root' => storage_path('app/imagepresets'),
    'throw' => false,
],
```

```env
IMAGEPRESET_DISK=imagepresets
```

## Remote disks (S3, GCS, FTP, …)

Any disk whose driver is not `local`.

```env
IMAGEPRESET_DISK=s3
IMAGEPRESET_PATH=imagepresets
```

On the first request:

1. Glide writes the result into `local_cache_dir` (local).
2. The file is uploaded to the disk.
3. The local copy is deleted — only if the upload succeeded.
4. The response is streamed from the disk, or redirected (below).

Every following request checks `exists()` on the disk and, when streaming, reads `lastModified()` and `size()` for the headers — a few storage API calls per request. A CDN in front of the endpoint absorbs most of them.

### Redirect to a presigned URL

By default the file is streamed through PHP. With redirect mode the response is a `302` to `temporaryUrl()`:

```env
IMAGEPRESET_REMOTE_REDIRECT=true
IMAGEPRESET_REMOTE_REDIRECT_TTL=300
```

| | Streaming (default) | Redirect |
|---|---|---|
| Traffic through PHP | Yes | No |
| Storage URL visible to the client | No | Yes, presigned, expires after `remote_redirect_ttl` |
| Cache headers of the endpoint response | `public, max-age=…, immutable` | Laravel's redirect defaults — the redirect itself is not cached by a CDN |

If the driver can't make temporary URLs (it throws `RuntimeException`, e.g. FTP or local), the package falls back to streaming. Redirect mode is applied to SVG too.

> [!WARNING]
> `imagepresets:verify` reads files through local paths and works only with local disks. On a remote disk it would report every file as corrupted — and delete them with `--delete`. See [Cache maintenance](cache-maintenance.md).
