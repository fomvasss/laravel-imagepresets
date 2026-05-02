<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Support;

/**
 * Normalises a remote URL to its canonical form:
 * - scheme and host are lowercased;
 * - IDN host is converted to ASCII;
 * - path / query / fragment percent-encoding is normalised.
 */
final class RemoteUrlNormalizer
{
    public function isRemote(string $src): bool
    {
        $lower = strtolower(substr($src, 0, 8));

        return str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://');
    }

    /**
     * @return string|false Normalised URL, or false on error.
     */
    public function normalize(string $url): string|false
    {
        if (!$this->isRemote($url)) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = (string) $parts['host'];
        if (function_exists('idn_to_ascii')) {
            $asciiHost = idn_to_ascii($host, IDNA_DEFAULT);
            if ($asciiHost === false) {
                return false;
            }
            $host = $asciiHost;
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        if ($path !== '') {
            $segments = explode('/', $path);
            $path = implode('/', array_map(
                static fn (string $s): string => rawurlencode(rawurldecode($s)),
                $segments,
            ));
        }

        $query = '';
        if (isset($parts['query']) && $parts['query'] !== '') {
            $pairs = explode('&', (string) $parts['query']);
            $pairs = array_map(static function (string $pair): string {
                if ($pair === '') {
                    return '';
                }
                $chunks = explode('=', $pair, 2);
                $key    = rawurlencode(rawurldecode($chunks[0]));

                return count($chunks) === 1
                    ? $key
                    : $key.'='.rawurlencode(rawurldecode($chunks[1]));
            }, $pairs);
            $query = implode('&', $pairs);
        }

        $normalized = $scheme.'://'.$host;
        if (isset($parts['port'])) {
            $normalized .= ':'.(int) $parts['port'];
        }
        $normalized .= $path;
        if ($query !== '') {
            $normalized .= '?'.$query;
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $normalized .= '#'.rawurlencode(rawurldecode((string) $parts['fragment']));
        }

        return $normalized;
    }

    /**
     * Returns the host extracted from the URL, or an empty string.
     */
    public function extractHost(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?? '');
    }
}
