<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Artisan command to clear the image preset cache.
 *
 * Usage:
 *   php artisan imagepresets:clear
 *   php artisan imagepresets:clear --disk=public --path=imagepresets
 *
 * Recommended to run after every deployment.
 */
final class ClearCommand extends Command
{
    protected $signature = 'imagepresets:clear
        {--disk= : Disk name (defaults to imagepresets.disk config value)}
        {--path= : Subdirectory inside the disk (defaults to imagepresets.path config value)}
        {--temp  : Also clear temporary directories (source_dir, temp_dir)}';

    protected $description = 'Clear the processed image preset cache';

    public function handle(): int
    {
        $disk = $this->option('disk') ?: config('imagepresets.disk', 'public');
        $path = $this->option('path') ?: config('imagepresets.path', 'imagepresets');

        $storage = Storage::disk((string) $disk);

        if ($storage->exists((string) $path)) {
            $storage->deleteDirectory((string) $path);
            $this->info("Preset cache cleared: disk={$disk}, path={$path}");
        } else {
            $this->line("Cache directory does not exist or is already empty: disk={$disk}, path={$path}");
        }

        if ($this->option('temp')) {
            $this->clearTempDir('source_dir', 'imagepreset_sources');
            $this->clearTempDir('temp_dir', 'imagepreset_temp');
        }

        return self::SUCCESS;
    }

    private function clearTempDir(string $configKey, string $fallback): void
    {
        $dir = (string) config("imagepresets.{$configKey}", storage_path("app/{$fallback}"));

        if (is_dir($dir)) {
            File::cleanDirectory($dir);
            $this->info("Temporary directory cleared: {$dir}");
        } else {
            $this->line("Temporary directory does not exist: {$dir}");
        }
    }
}
