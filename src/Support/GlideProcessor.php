<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Support;

use Illuminate\Support\Facades\File;
use League\Glide\Filesystem\FileNotFoundException;
use League\Glide\Filesystem\FilesystemException;
use League\Glide\ServerFactory;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * Responsible for:
 * — building Glide parameters (buildParams / outputExtension);
 * — processing raster images via league/glide (process).
 */
final class GlideProcessor
{
    /**
     * Builds the Glide parameter array from validated request data.
     */
    public function buildParams(array $validated): array
    {
        $hasW = $this->hasParam($validated, 'w');
        $hasH = $this->hasParam($validated, 'h');

        $quality = (string) (int) ($validated['q'] ?? config('imagepresets.quality', 80));
        $format  = (string) ($validated['fm'] ?? config('imagepresets.format', 'webp'));

        $params = ['q' => $quality, 'fm' => $format];

        if ($hasW) {
            $params['w'] = (string) (int) $validated['w'];
        }
        if ($hasH) {
            $params['h'] = (string) (int) $validated['h'];
        }

        if ($hasW || $hasH) {
            if (isset($validated['fit'])) {
                $params['fit'] = (string) $validated['fit'];
            } elseif ($hasW && $hasH) {
                $params['fit'] = (string) config('imagepresets.default_fit_both', 'fill');
            } else {
                $params['fit'] = (string) config('imagepresets.default_fit_one', 'max');
            }
        }

        // Additional manipulation params — applied regardless of resize
        foreach (['blur', 'sharp', 'or', 'crop', 'bg'] as $key) {
            if ($this->hasParam($validated, $key)) {
                $params[$key] = (string) $validated[$key];
            }
        }

        return $params;
    }

    /**
     * Determines the output file extension.
     * pjpg is a Glide-specific alias for progressive JPEG; stored on disk as .jpg.
     */
    public function outputExtension(array $validated, array $glideParams): string
    {
        $fm = (string) ($validated['fm'] ?? $glideParams['fm'] ?? config('imagepresets.format', 'webp'));

        return match ($fm) {
            'pjpg', 'jpg', 'jpeg' => 'jpg',
            default                => $fm,
        };
    }

    /**
     * Copies the source to the working directory, runs Glide, then removes the copy.
     * Returns true on success, false on error.
     */
    public function process(
        string $sourcePath,
        string $sourceSrc,
        string $cacheRoot,
        string $subPath,
        string $presetName,
        array $glideParams,
    ): bool {
        $sourceDir = $this->getSourceDir();
        if (!is_dir($sourceDir)) {
            File::makeDirectory($sourceDir, 0755, true);
        }

        $ext          = $this->guessSourceExtension($sourcePath, $sourceSrc);
        $workName     = md5($sourceSrc).'.'.$ext;
        $workPath     = $sourceDir.DIRECTORY_SEPARATOR.$workName;

        if (!@copy($sourcePath, $workPath)) {
            return false;
        }

        $server = $this->createServer($cacheRoot, $sourceDir, $subPath, $presetName);

        try {
            $server->makeImage($workName, $glideParams);
        } catch (FileNotFoundException|FilesystemException) {
            @unlink($workPath);

            return false;
        } catch (Throwable $e) {
            @unlink($workPath);

            if (config('app.debug')) {
                Log::error('[Imagepresets] makeImage failed', [
                    'src'     => $sourceSrc,
                    'message' => $e->getMessage(),
                    'class'   => $e::class,
                ]);
            }

            return false;
        }

        @unlink($workPath);

        return true;
    }

    public function getTempDir(): string
    {
        return (string) config('imagepresets.temp_dir', storage_path('app/imagepreset_temp'));
    }

    // -------------------------------------------------------------------------
    // Private methods
    // -------------------------------------------------------------------------

    private function createServer(
        string $cacheRoot,
        string $sourceDir,
        string $subPath,
        string $presetName,
    ): \League\Glide\Server {
        $tempDir = $this->getTempDir();
        if (!is_dir($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $relOut = rtrim($subPath, '/').'/'.$presetName;

        // Not static: Glide calls Closure::bind($callable, $this, Server) internally for getCachePath.
        $cachePathCallable = function (string $path, array $params) use ($relOut): string {
            return $relOut;
        };

        return ServerFactory::create([
            'source'                => $sourceDir,
            'cache'                 => $cacheRoot,
            'group_cache_in_folders' => false,
            'cache_path_callable'   => $cachePathCallable,
            'temp_dir'              => $tempDir,
            'driver'                => (string) config('imagepresets.driver', 'gd'),
        ]);
    }

    private function guessSourceExtension(string $sourcePath, string $originalSrc): string
    {
        $urlPath = (string) parse_url($originalSrc, PHP_URL_PATH);
        if (preg_match('/\.([a-z0-9]{1,5})$/i', $urlPath, $m)) {
            return strtolower($m[1]);
        }

        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
        if ($ext !== '') {
            return strtolower($ext);
        }

        return 'jpg';
    }

    private function hasParam(array $data, string $key): bool
    {
        return ($data[$key] ?? null) !== null && $data[$key] !== '';
    }
}
