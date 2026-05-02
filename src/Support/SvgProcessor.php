<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Support;

use Illuminate\Filesystem\FilesystemAdapter;

/**
 * Processes SVG images: sanitizes (when enabled) and stores the result in cache.
 *
 * Full sanitization requires enshrined/svg-sanitize:
 *   composer require enshrined/svg-sanitize
 *
 * Extensibility: optimization, viewBox transform support, etc.
 */
final class SvgProcessor
{
    /**
     * Stores an SVG to the cache disk and returns the absolute path.
     * Returns the existing path if the file is already cached.
     */
    public function process(
        FilesystemAdapter $disk,
        string $subPath,
        string $presetName,
        string $sourcePath,
    ): ?string {
        $relPreset = $subPath.'/'.$presetName;

        if ($disk->exists($relPreset)) {
            return $disk->path($relPreset);
        }

        $content = @file_get_contents($sourcePath);
        if ($content === false || $content === '') {
            return null;
        }

        if ((bool) config('imagepresets.svg.sanitize', true)) {
            $content = $this->sanitize($content);
            if ($content === null) {
                return null;
            }
        }

        if (!$disk->put($relPreset, $content)) {
            return null;
        }

        return $disk->path($relPreset);
    }

    // -------------------------------------------------------------------------
    // Private methods
    // -------------------------------------------------------------------------

    /**
     * Sanitizes SVG content.
     * Uses enshrined/svg-sanitize when available; otherwise falls back to basic regex sanitization.
     */
    private function sanitize(string $content): ?string
    {
        if (class_exists(\enshrined\svgSanitize\Sanitizer::class)) {
            $sanitizer = new \enshrined\svgSanitize\Sanitizer();
            $sanitizer->removeRemoteReferences(
                (bool) config('imagepresets.svg.remove_remote_references', true)
            );
            $result = $sanitizer->sanitize($content);

            return $result === false ? null : $result;
        }

        // Basic sanitization without third-party libraries:
        // removes <script> tags, on* event attributes, and external references.
        return $this->basicSanitize($content);
    }

    private function basicSanitize(string $content): string
    {
        // Remove <script>...</script> blocks
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content) ?? $content;

        // Remove on* event attributes (onload, onclick, etc.)
        $content = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $content) ?? $content;

        // Remove javascript: URIs
        $content = preg_replace('/\bjavascript\s*:/i', '', $content) ?? $content;

        return $content;
    }
}
