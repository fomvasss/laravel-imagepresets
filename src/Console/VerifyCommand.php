<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Console;

use Fomvasss\Imagepresets\Support\ImageIntegrity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Finds (and optionally removes) corrupted/truncated cached preset files, plus
 * orphaned *.tmp* leftovers from processes killed before the atomic rename.
 *
 * Usage:
 *   php artisan imagepresets:verify
 *   php artisan imagepresets:verify --delete
 *   php artisan imagepresets:verify --disk=public --path=imagepresets --delete
 */
final class VerifyCommand extends Command
{
    protected $signature = 'imagepresets:verify
        {--disk= : Disk name (defaults to imagepresets.disk config value)}
        {--path= : Subdirectory inside the disk (defaults to imagepresets.path config value)}
        {--delete : Remove the corrupted/orphaned files found (default: dry run, list only)}';

    protected $description = 'Find corrupted preset cache files (truncated encode/write) and orphaned temp files';

    private const KNOWN_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('imagepresets.disk', 'public'));
        $path     = (string) ($this->option('path') ?: config('imagepresets.path', ''));
        $delete   = (bool) $this->option('delete');

        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            $this->line("Cache directory does not exist or is already empty: disk={$diskName}, path={$path}");

            return self::SUCCESS;
        }

        $corrupted = [];
        $orphaned  = [];

        foreach ($disk->allFiles($path) as $file) {
            if (str_contains($file, '.tmp')) {
                if ($disk->lastModified($file) < now()->subHour()->timestamp) {
                    $orphaned[] = $file;
                }

                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, self::KNOWN_EXTENSIONS, true)) {
                continue;
            }

            if (!ImageIntegrity::isValid($disk->path($file), $ext)) {
                $corrupted[] = $file;
            }
        }

        foreach ($corrupted as $file) {
            $this->line($delete ? "Corrupted (deleted): {$file}" : "Corrupted: {$file}");
        }

        foreach ($orphaned as $file) {
            $this->line($delete ? "Orphaned tmp (deleted): {$file}" : "Orphaned tmp: {$file}");
        }

        if ($delete) {
            foreach ([...$corrupted, ...$orphaned] as $file) {
                $disk->delete($file);
            }
        }

        $this->info(sprintf(
            'Found %d corrupted file(s) and %d orphaned tmp file(s)%s.',
            count($corrupted),
            count($orphaned),
            $delete ? ' — deleted' : ' — run with --delete to remove',
        ));

        return self::SUCCESS;
    }
}
