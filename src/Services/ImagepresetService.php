<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Services;

use Fomvasss\Imagepresets\Support\GlideProcessor;
use Fomvasss\Imagepresets\Support\ResponseBuilder;
use Fomvasss\Imagepresets\Support\SourceResolver;
use Fomvasss\Imagepresets\Support\SvgProcessor;
use Fomvasss\Imagepresets\Validation\ImagepresetValidator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orchestrates the full preset processing pipeline:
 * validation → source resolution → processing (SVG or raster) → HTTP response.
 *
 * Supports both local disks (public, local) and remote disks (S3, GCS, etc.).
 * For remote disks Glide processes images locally into local_cache_dir,
 * after which the result is uploaded to the configured disk and served as a stream.
 */
final class ImagepresetService
{
    public function __construct(
        private readonly ImagepresetValidator $validator,
        private readonly SourceResolver       $sourceResolver,
        private readonly GlideProcessor       $glideProcessor,
        private readonly SvgProcessor         $svgProcessor,
        private readonly ResponseBuilder      $responseBuilder,
    ) {}

    public function handle(Request $request): BinaryFileResponse|StreamedResponse|Response
    {
        try {
            $validated = $this->validator->validate($request);
        } catch (ValidationException) {
            abort(404);
        }

        // Merge named preset params as defaults; explicit request params take priority.
        $validated = $this->applyPreset($validated);

        $this->auditLog($validated, $request);

        ['disk' => $disk, 'subPath' => $subPath, 'cacheRoot' => $cacheRoot, 'isLocal' => $isLocal]
            = $this->resolveDisk();

        $sourcePath = $this->sourceResolver->resolve($validated);
        if ($sourcePath === null || !is_file($sourcePath)) {
            abort(404);
        }

        $isSvg = $this->sourceResolver->isSvg($sourcePath, (string) $validated['src']);

        if ($isSvg && $this->shouldRasterizeSvg($validated)) {
            // SVG → raster via Glide (requires driver=imagick)
            return $this->processRaster($request, $validated, $disk, $cacheRoot, $subPath, $sourcePath, $isLocal);
        }

        if ($isSvg) {
            return $this->processSvg($validated, $disk, $subPath, $sourcePath, $isLocal);
        }

        return $this->processRaster($request, $validated, $disk, $cacheRoot, $subPath, $sourcePath, $isLocal);
    }

    /**
     * Generates a URL to an image preset.
     *
     * @param  array|string  $params  Associative array of params OR a named preset string.
     */
    public function url(string $src, array|string $params = []): string
    {
        if (is_string($params)) {
            $params = ['preset' => $params];
        }

        $params = array_merge(['src' => $src], $params);
        ksort($params);

        $routeName = (string) config('imagepresets.route.name', 'imagepreset');

        if ((bool) config('imagepresets.route.signed', false)) {
            return URL::signedRoute($routeName, $params);
        }

        return route($routeName, $params);
    }

    // -------------------------------------------------------------------------
    // Private methods
    // -------------------------------------------------------------------------

    /**
     * Logs validated request params when audit_log is enabled.
     * Useful in local/staging to discover which sizes the frontend actually requests.
     */
    private function auditLog(array $validated, Request $request): void
    {
        if (!(bool) config('imagepresets.audit_log.enabled', false)) {
            return;
        }

        // only_new=true: skip if this exact combination is already cached on disk
        if ((bool) config('imagepresets.audit_log.only_new', true)) {
            ['disk' => $disk, 'subPath' => $subPath] = $this->resolveDisk();
            $glideParams = $this->glideProcessor->buildParams($validated);
            $ext         = $this->glideProcessor->outputExtension($validated, $glideParams);
            $presetName  = $this->buildPresetFileName($request, $ext);

            if ($disk->exists($subPath.'/'.$presetName)) {
                return;
            }
        }

        $keys    = ['src', 'preset', 'w', 'h', 'q', 'fm', 'fit', 'blur', 'sharp', 'or', 'crop', 'bg'];
        $params  = array_filter(
            array_intersect_key($validated, array_flip($keys)),
            fn ($v) => $v !== null && $v !== '',
        );

        Log::info('imagepreset_request', [
            'params' => $params,
            'ip'     => $request->ip(),
            'url'    => $request->fullUrl(),
        ]);
    }

    /**
     * Merges named preset params (from config) into validated data.
     * Preset params serve as defaults — explicit request params take priority.
     * The `preset` key is removed from the result before further processing.
     */
    private function applyPreset(array $validated): array
    {
        $presetName = $validated['preset'] ?? null;
        unset($validated['preset']);

        if ($presetName === null) {
            return $validated;
        }

        $presetParams = (array) config('imagepresets.presets.'.$presetName, []);

        // Preset is the base; non-null request params override it.
        $merged = $presetParams;
        foreach ($validated as $key => $value) {
            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Determines whether the SVG should be rasterized.
     * Conditions: rasterize=true + driver=imagick + at least one transform param.
     */
    private function shouldRasterizeSvg(array $validated): bool
    {
        if (!(bool) config('imagepresets.svg.rasterize', false)) {
            return false;
        }

        if (strtolower((string) config('imagepresets.driver', 'gd')) !== 'imagick') {
            return false;
        }

        $hasTransform = ($validated['w'] ?? null) !== null
            || ($validated['h'] ?? null) !== null
            || ($validated['fm'] ?? null) !== null;

        return $hasTransform;
    }

    /**
     * Resolves the configured disk and determines whether it is a local disk.
     *
     * Local disk  — has a 'root' key in filesystems config pointing to an existing directory.
     * Remote disk — S3, GCS, FTP, etc. Glide writes to local_cache_dir; the result is
     *               uploaded to the remote disk afterwards.
     */
    private function resolveDisk(): array
    {
        $diskName = (string) config('imagepresets.disk', 'public');
        $subPath  = ltrim((string) config('imagepresets.path', 'imagepresets'), '/');

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        $configRoot = (string) config("filesystems.disks.{$diskName}.root", '');
        $isLocal    = $configRoot !== '' && is_dir($configRoot);

        if ($isLocal) {
            $cacheRoot = $configRoot;
        } else {
            // For remote disks Glide needs a writable local directory.
            $cacheRoot = rtrim(
                (string) config('imagepresets.local_cache_dir', storage_path('app/imagepreset_glide_cache')),
                '/',
            );

            if (!is_dir($cacheRoot)) {
                File::makeDirectory($cacheRoot, 0755, true);
            }
        }

        return compact('disk', 'diskName', 'subPath', 'cacheRoot', 'isLocal');
    }

    private function buildPresetFileName(Request $request, string $ext): string
    {
        $data = $request->query();
        ksort($data);

        return md5((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'.'.$ext;
    }

    private function ensureOutputDirectory(FilesystemAdapter $disk, string $subPath): void
    {
        // source_dir (always local)
        $sourceDir = $this->sourceResolver->getSourceDir();
        if (!is_dir($sourceDir)) {
            File::makeDirectory($sourceDir, 0755, true);
        }

        // temp_dir (always local)
        $tempDir = $this->glideProcessor->getTempDir();
        if (!is_dir($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        // Cache subdirectory on disk (Flysystem handles both local and remote).
        if (!$disk->exists($subPath)) {
            $disk->makeDirectory($subPath);
        }
    }

    private function processSvg(
        array $validated,
        FilesystemAdapter $disk,
        string $subPath,
        string $sourcePath,
        bool $isLocal,
    ): BinaryFileResponse|StreamedResponse|Response {
        // SVG transforms (w/h/q/fit/fm) are not applied —
        // cache key is built from src only to avoid duplicates.
        $presetName = md5((string) $validated['src']).'.svg';
        $relPreset  = $subPath.'/'.$presetName;

        $this->ensureOutputDirectory($disk, $subPath);

        $outputPath = $this->svgProcessor->process($disk, $subPath, $presetName, $sourcePath);

        $this->sourceResolver->cleanupTemp($sourcePath);

        if ($outputPath === null) {
            abort(404);
        }

        if ($isLocal) {
            return $this->responseBuilder->build($outputPath, 'svg');
        }

        // Remote disk: stream directly from Flysystem
        return $this->responseBuilder->buildFromDisk($disk, $relPreset, 'svg');
    }

    private function processRaster(
        Request $request,
        array $validated,
        FilesystemAdapter $disk,
        string $cacheRoot,
        string $subPath,
        string $sourcePath,
        bool $isLocal,
    ): BinaryFileResponse|StreamedResponse|Response {
        $glideParams = $this->glideProcessor->buildParams($validated);
        $ext         = $this->glideProcessor->outputExtension($validated, $glideParams);
        $presetName  = $this->buildPresetFileName($request, $ext);
        $relPreset   = $subPath.'/'.$presetName;

        // Already cached — return immediately
        if ($disk->exists($relPreset)) {
            return $this->buildResponse($disk, $relPreset, $ext, $isLocal);
        }

        // Race condition: only one process generates the file when concurrent requests arrive
        $lock = Cache::lock('imagepreset:'.$presetName, 30);

        try {
            $lock->block(15);

            // Double-check after acquiring the lock
            if ($disk->exists($relPreset)) {
                return $this->buildResponse($disk, $relPreset, $ext, $isLocal);
            }

            $this->ensureOutputDirectory($disk, $subPath);

            $success = $this->glideProcessor->process(
                sourcePath:  $sourcePath,
                sourceSrc:   (string) $validated['src'],
                cacheRoot:   $cacheRoot,
                subPath:     $subPath,
                presetName:  $presetName,
                glideParams: $glideParams,
            );

            $this->sourceResolver->cleanupTemp($sourcePath);

            if (!$success) {
                abort(404);
            }

            // For remote disks: upload Glide output from local cache to the remote disk.
            if (!$isLocal) {
                $this->uploadToRemoteDisk($disk, $cacheRoot, $relPreset);
            }

            if (!$disk->exists($relPreset)) {
                abort(404);
            }
        } finally {
            $lock->forceRelease();
        }

        return $this->buildResponse($disk, $relPreset, $ext, $isLocal);
    }

    /**
     * Builds the appropriate HTTP response depending on disk type.
     */
    private function buildResponse(
        FilesystemAdapter $disk,
        string $relPreset,
        string $ext,
        bool $isLocal,
    ): BinaryFileResponse|StreamedResponse|Response {
        if ($isLocal) {
            return $this->responseBuilder->build($disk->path($relPreset), $ext);
        }

        return $this->responseBuilder->buildFromDisk($disk, $relPreset, $ext);
    }

    /**
     * Uploads a locally processed file to the remote disk, then removes the local copy.
     */
    private function uploadToRemoteDisk(
        FilesystemAdapter $disk,
        string $localCacheRoot,
        string $relPreset,
    ): void {
        $localPath = rtrim($localCacheRoot, '/').'/'.$relPreset;

        if (!is_file($localPath)) {
            return;
        }

        $fp = fopen($localPath, 'rb');
        if (is_resource($fp)) {
            $disk->put($relPreset, $fp);
            fclose($fp);
        }

        @unlink($localPath);

        // Remove empty parent directory if left behind
        $localDir = dirname($localPath);
        if (is_dir($localDir) && count(scandir($localDir)) === 2) {
            @rmdir($localDir);
        }
    }
}
