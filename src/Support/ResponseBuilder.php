<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds an HTTP response for an image file with appropriate cache headers.
 */
final class ResponseBuilder
{
    private const MIME_MAP = [
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
    ];

    public function build(string $absolutePath, string $ext, bool $isNew = false): BinaryFileResponse|Response
    {
        $mime         = self::MIME_MAP[$ext] ?? 'application/octet-stream';
        $mtime        = (int) @filemtime($absolutePath);
        $size         = (int) @filesize($absolutePath);
        $etag         = '"'.base_convert((string) $mtime, 10, 36).'-'.base_convert((string) $size, 10, 36).'"';
        $lastModified = gmdate('D, d M Y H:i:s', $mtime).' GMT';
        $maxAge       = (int) config('imagepresets.cache_max_age', 31536000);

        // For newly generated files, prevent any caching (no-store).
        // For cached files, use long-term caching (immutable).
        $cacheControl = $isNew
            ? 'no-store'
            : "public, max-age={$maxAge}, s-maxage={$maxAge}, immutable";

        $headers = [
            'Content-Type'           => $mime,
            'Cache-Control'          => $cacheControl,
            'ETag'                   => $etag,
            'Last-Modified'          => $lastModified,
            // Show inline when the URL is opened directly (not as a download)
            'Content-Disposition'    => 'inline',
            // Prevent the browser from sniffing the content type
            'X-Content-Type-Options' => 'nosniff',
        ];

        // SVG may contain active content — disallow script execution
        if ($ext === 'svg') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($absolutePath, $headers);
    }

    /**
     * Builds a streamed HTTP response for a file stored on a remote Flysystem disk (S3, GCS, etc.).
     * Metadata (size, mtime) is fetched via Flysystem; the file body is streamed without buffering.
     */
    public function buildFromDisk(FilesystemAdapter $disk, string $relPath, string $ext, bool $isNew = false): StreamedResponse
    {
        $mime    = self::MIME_MAP[$ext] ?? 'application/octet-stream';
        $maxAge  = (int) config('imagepresets.cache_max_age', 31536000);
        $mtime   = (int) $disk->lastModified($relPath);
        $size    = (int) $disk->size($relPath);
        $etag    = '"'.base_convert((string) $mtime, 10, 36).'-'.base_convert((string) $size, 10, 36).'"';
        $lastMod = gmdate('D, d M Y H:i:s', $mtime).' GMT';

        // For newly generated files, prevent any caching (no-store).
        // For cached files, use long-term caching (immutable).
        $cacheControl = $isNew
            ? 'no-store'
            : "public, max-age={$maxAge}, s-maxage={$maxAge}, immutable";

        $headers = [
            'Content-Type'           => $mime,
            'Content-Length'         => $size,
            'Cache-Control'          => $cacheControl,
            'ETag'                   => $etag,
            'Last-Modified'          => $lastMod,
            'Content-Disposition'    => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($ext === 'svg') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->stream(function () use ($disk, $relPath): void {
            $stream = $disk->readStream($relPath);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }
}
