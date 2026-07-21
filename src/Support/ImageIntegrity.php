<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Support;

/**
 * Detects truncated image files (e.g. from an interrupted encode/write) by checking
 * format-specific end-of-file markers. Unlike getimagesize(), which only reads the
 * header, this catches a file that stops partway through the pixel data.
 */
final class ImageIntegrity
{
    public static function isValid(string $absolutePath, string $ext): bool
    {
        $size = @filesize($absolutePath);
        if ($size === false || $size === 0) {
            return false;
        }

        return match (strtolower($ext)) {
            'jpg', 'jpeg' => self::endsWith($absolutePath, $size, "\xFF\xD9"),
            'png'         => self::endsWith($absolutePath, $size, "\x49\x45\x4E\x44\xAE\x42\x60\x82"),
            'gif'         => self::endsWith($absolutePath, $size, "\x3B"),
            'webp'        => self::isValidWebp($absolutePath, $size),
            // ISOBMFF-based containers (avif, heic, ...) have no simple trailing marker —
            // a non-empty file that at least parses as an image is the best we can cheaply verify.
            default       => @getimagesize($absolutePath) !== false,
        };
    }

    private static function endsWith(string $path, int $size, string $marker): bool
    {
        $tail = @file_get_contents($path, false, null, max(0, $size - strlen($marker)), strlen($marker));

        return $tail === $marker;
    }

    private static function isValidWebp(string $path, int $size): bool
    {
        if ($size < 12) {
            return false;
        }

        $header = @file_get_contents($path, false, null, 0, 8);
        if ($header === false || substr($header, 0, 4) !== 'RIFF') {
            return false;
        }

        $riffSize = unpack('V', substr($header, 4, 4))[1] ?? null;

        return $riffSize !== null && $riffSize + 8 === $size;
    }
}
