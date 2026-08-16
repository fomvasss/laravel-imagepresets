<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Console;

use Fomvasss\Imagepresets\Support\ImageIntegrity;
use Fomvasss\Imagepresets\Support\WebpDecodeCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Finds (and optionally removes) corrupted/truncated cached preset files, plus
 * orphaned *.tmp* leftovers from processes killed before the atomic rename.
 *
 * With --deep, additionally decodes every webp that passed the structural check
 * and flags files carrying the solid-gray damaged-payload filler as "suspects".
 * Suspects are a heuristic (genuinely gray images can be false positives), so
 * --delete never touches them — review the list, then use --delete-suspects.
 *
 * Usage:
 *   php artisan imagepresets:verify
 *   php artisan imagepresets:verify --delete
 *   php artisan imagepresets:verify --deep
 *   php artisan imagepresets:verify --deep --gray-threshold=40
 *   php artisan imagepresets:verify --delete --delete-suspects
 *   php artisan imagepresets:verify --disk=public --path=imagepresets --delete
 */
final class VerifyCommand extends Command
{
    protected $signature = 'imagepresets:verify
        {--disk= : Disk name (defaults to imagepresets.disk config value)}
        {--path= : Subdirectory inside the disk (defaults to imagepresets.path config value)}
        {--delete : Remove the corrupted/orphaned files found (default: dry run, list only)}
        {--deep : Additionally decode each webp and flag damaged-payload files (suspects)}
        {--gray-threshold=25 : Percentage of gray filler samples above which a webp is a suspect (with --deep)}
        {--delete-suspects : Remove the suspect webp files found by --deep (review the list first)}';

    protected $description = 'Find corrupted preset cache files (truncated encode/write) and orphaned temp files';

    private const KNOWN_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('imagepresets.disk', 'public'));
        $path = (string) ($this->option('path') ?: config('imagepresets.path', ''));
        $delete = (bool) $this->option('delete');
        $deleteSuspects = (bool) $this->option('delete-suspects');
        $deep = (bool) $this->option('deep') || $deleteSuspects;
        $grayThreshold = max(0, min(100, (int) $this->option('gray-threshold'))) / 100;

        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            $this->line("Cache directory does not exist or is already empty: disk={$diskName}, path={$path}");

            return self::SUCCESS;
        }

        $corrupted = [];
        $orphaned = [];
        $suspects = [];

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

                continue;
            }

            if ($deep && $ext === 'webp') {
                $reason = WebpDecodeCheck::isSuspicious($disk->path($file), $grayThreshold);
                if ($reason !== null) {
                    $suspects[$file] = $reason;
                }
            }
        }

        foreach ($corrupted as $file) {
            $this->line($delete ? "Corrupted (deleted): {$file}" : "Corrupted: {$file}");
        }

        foreach ($orphaned as $file) {
            $this->line($delete ? "Orphaned tmp (deleted): {$file}" : "Orphaned tmp: {$file}");
        }

        foreach ($suspects as $file => $reason) {
            $this->line($deleteSuspects ? "Suspect webp (deleted): {$file} — {$reason}" : "Suspect webp: {$file} — {$reason}");
        }

        if ($delete) {
            foreach ([...$corrupted, ...$orphaned] as $file) {
                $disk->delete($file);
            }
        }

        if ($deleteSuspects) {
            foreach (array_keys($suspects) as $file) {
                $disk->delete($file);
            }
        }

        $summary = sprintf(
            'Found %d corrupted file(s) and %d orphaned tmp file(s)%s.',
            count($corrupted),
            count($orphaned),
            $delete ? ' — deleted' : ' — run with --delete to remove',
        );

        if ($deep) {
            $summary .= sprintf(
                ' Found %d suspect webp file(s)%s.',
                count($suspects),
                $deleteSuspects ? ' — deleted' : ' — review, then run with --delete-suspects to remove',
            );
        }

        $this->info($summary);

        return self::SUCCESS;
    }
}
