<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Support;

/**
 * Detects webp files whose VP8 payload is damaged even though the RIFF container
 * size is consistent (e.g. the tail of the payload was never flushed to disk — the
 * file length is right, so ImageIntegrity passes, but libwebp hits the broken data
 * mid-decode and fills the remaining macroblock rows with solid rgb(128,128,128)).
 *
 * Decodes the file with GD and samples a grid of pixels: a large share of exact
 * rgb(128,128,128) samples is the signature of that gray filler. Images with
 * genuinely flat-gray areas can trigger a false positive — treat a non-null
 * result as "suspect", not proof.
 */
final class WebpDecodeCheck
{
    public const DEFAULT_GRAY_THRESHOLD = 0.25;

    private const GRID = 12;

    /**
     * Returns a human-readable reason when the file looks damaged, null when it
     * passes. Also returns null when GD webp support is unavailable (nothing can
     * be checked). $grayThreshold is the share (0..1) of exact-gray samples above
     * which the file is flagged.
     */
    public static function isSuspicious(string $absolutePath, float $grayThreshold = self::DEFAULT_GRAY_THRESHOLD): ?string
    {
        if (!function_exists('imagecreatefromwebp')) {
            return null;
        }

        $img = @imagecreatefromwebp($absolutePath);
        if ($img === false) {
            return 'webp payload does not decode';
        }

        $width = imagesx($img);
        $height = imagesy($img);
        if ($width < self::GRID || $height < self::GRID) {
            imagedestroy($img);

            return null;
        }

        $grayHits = 0;
        for ($row = 0; $row < self::GRID; $row++) {
            $y = (int) (($row + 0.5) / self::GRID * $height);
            for ($col = 0; $col < self::GRID; $col++) {
                $x = (int) (($col + 0.5) / self::GRID * $width);
                $rgb = imagecolorat($img, $x, $y);
                if ((($rgb >> 16) & 0xFF) === 128 && (($rgb >> 8) & 0xFF) === 128 && ($rgb & 0xFF) === 128) {
                    $grayHits++;
                }
            }
        }
        imagedestroy($img);

        $share = $grayHits / (self::GRID ** 2);
        if ($share > $grayThreshold) {
            return sprintf('%d%% of sampled pixels are the rgb(128,128,128) decoder filler', (int) round($share * 100));
        }

        return null;
    }
}
