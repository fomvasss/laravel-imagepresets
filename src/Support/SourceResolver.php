<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the src parameter (local or remote URL) to an absolute file path.
 * Responsible for downloading remote images and cleaning up temporary files.
 */
final class SourceResolver
{
    public function __construct(
        private readonly RemoteUrlNormalizer $normalizer,
    ) {}

    /**
     * Returns the absolute path, or null if the source is unavailable.
     */
    public function resolve(array $validated): ?string
    {
        $src = (string) $validated['src'];

        if ($this->normalizer->isRemote($src)) {
            return $this->resolveRemote($src);
        }

        return $this->resolveLocal($src);
    }

    /**
     * Determines whether the file is an SVG (by extension of the original src or local path).
     */
    public function isSvg(string $sourcePath, string $originalSrc): bool
    {
        $urlPath = rawurldecode((string) (parse_url($originalSrc, PHP_URL_PATH) ?? ''));
        if (strtolower(pathinfo($urlPath, PATHINFO_EXTENSION)) === 'svg') {
            return true;
        }

        return strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'svg';
    }

    /**
     * Deletes a temporary downloaded file (only if it is inside source_dir and has the dl_ prefix).
     */
    public function cleanupTemp(string $sourcePath): void
    {
        if (!$this->isTempDownload($sourcePath)) {
            return;
        }

        @unlink($sourcePath);
    }

    public function getSourceDir(): string
    {
        return (string) config('imagepresets.source_dir', storage_path('app/imagepreset_sources'));
    }

    /**
     * Finds the absolute path to a local file.
     * Priority: public disk → storage/ → public/.
     */
    public function findLocalPath(string $rel): ?string
    {
        if (str_contains($rel, '..') || $rel === '' || $rel[0] === '.') {
            return null;
        }

        $path = null;

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($rel)) {
            $path = $publicDisk->path($rel);
        }

        if ($path === null) {
            $underStorage = storage_path($rel);
            if (is_file($underStorage)) {
                $path = realpath($underStorage) ?: $underStorage;
            }
        }

        if ($path === null) {
            $underPublic = public_path($rel);
            if (is_file($underPublic)) {
                $path = realpath($underPublic) ?: $underPublic;
            }
        }

        if ($path === null) {
            return null;
        }

        // Image bomb: check raster local files (skip SVG)
        if (!$this->isSvg($path, $rel)) {
            $imageInfo = @getimagesize($path);
            if ($imageInfo !== false) {
                $maxPixels = (int) config('imagepresets.max_image_pixels', 150_000_000);
                if ($maxPixels > 0 && ($imageInfo[0] * $imageInfo[1]) > $maxPixels) {
                    return null;
                }
            }
        }

        return $path;
    }

    // -------------------------------------------------------------------------
    // Private methods
    // -------------------------------------------------------------------------

    private function resolveRemote(string $src): ?string
    {
        $normalized = $this->normalizer->normalize($src);
        if ($normalized === false) {
            return null;
        }

        // Same-origin URL — resolve to local file
        $local = $this->resolveSameOriginToLocal($normalized);
        if ($local !== null && is_file($local)) {
            return $local;
        }

        return $this->downloadToTemp($normalized);
    }

    private function resolveLocal(string $src): ?string
    {
        $rel = ltrim(str_replace(['\\', '//'], ['/', '/'], $src), '/');

        return $this->findLocalPath($rel);
    }

    private function resolveSameOriginToLocal(string $url): ?string
    {
        $host = $this->normalizer->extractHost($url);
        if ($host === '' || !$this->isAppUrlHost($host)) {
            return null;
        }

        $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        $storagePrefix = '/storage/';
        if (!str_starts_with($path, $storagePrefix)) {
            return null;
        }

        $rel = ltrim(str_replace(['\\', '//'], ['/', '/'], substr($path, strlen($storagePrefix))), '/');
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }

        return $this->findLocalPath($rel);
    }

    private function downloadToTemp(string $url): ?string
    {
        $maxBytes = (int) config('imagepresets.max_download_bytes', 20 * 1024 * 1024);

        try {
            $response = Http::timeout(30)
                ->withOptions([
                    'http_errors'     => false,
                    // SSRF protection: disallow redirects — final host is not validated again
                    'allow_redirects' => false,
                ])
                ->withHeaders(['User-Agent' => 'Laravel-Imagepresets/1.0'])
                ->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return null;
        }

        // Reject 3xx redirects as invalid responses
        if (!$response->successful()) {
            return null;
        }

        $contentLength = $response->header('Content-Length');
        if ($contentLength !== null && (int) $contentLength > $maxBytes) {
            return null;
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            return null;
        }

        $dir = $this->getSourceDir();
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $tmpPath = $dir.DIRECTORY_SEPARATOR.'dl_'.uniqid('', true);
        if (file_put_contents($tmpPath, $body) === false) {
            return null;
        }

        // Validate content
        $contentType = $response->header('Content-Type');
        if (
            $this->isSvgContentType($contentType)
            || $this->isSvg($tmpPath, $url)
            || $this->hasSvgPayload($tmpPath)
        ) {
            return $tmpPath;
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo !== false) {
            $maxPixels = (int) config('imagepresets.max_image_pixels', 150_000_000);
            if ($maxPixels > 0 && ($imageInfo[0] * $imageInfo[1]) > $maxPixels) {
                @unlink($tmpPath);

                return null;
            }
        }

        return $tmpPath;
    }

    private function isTempDownload(string $sourcePath): bool
    {
        $workDir = realpath($this->getSourceDir()) ?: '';
        if ($workDir === '' || !is_file($sourcePath)) {
            return false;
        }

        $realPath = realpath($sourcePath) ?: $sourcePath;
        if (!str_starts_with($realPath, $workDir) || $realPath === $workDir) {
            return false;
        }

        return str_starts_with(basename($realPath), 'dl_');
    }

    private function isAppUrlHost(string $host): bool
    {
        $appHost = (string) (parse_url((string) config('app.url', ''), PHP_URL_HOST) ?? '');

        return $appHost !== '' && strtolower($host) === strtolower($appHost);
    }

    private function isSvgContentType(?string $contentType): bool
    {
        return is_string($contentType) && str_contains(strtolower($contentType), 'image/svg+xml');
    }

    private function hasSvgPayload(string $path): bool
    {
        $head = @file_get_contents($path, false, null, 0, 2048);

        return is_string($head) && $head !== '' && preg_match('/<svg\b/i', $head) === 1;
    }
}

