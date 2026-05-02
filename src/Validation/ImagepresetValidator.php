<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Validation;

use Fomvasss\Imagepresets\Support\RemoteUrlNormalizer;
use Fomvasss\Imagepresets\Support\SourceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validates incoming request parameters for the preset endpoint.
 * Checks the validity of src (remote/local), dimensions, quality, and fit.
 */
final class ImagepresetValidator
{
    public function __construct(
        private readonly RemoteUrlNormalizer $normalizer,
        private readonly SourceResolver $sourceResolver,
    ) {}

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(Request $request): array
    {
        $validator = Validator::make($request->all(), $this->baseRules());

        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $this->validateSource($v);
            $this->validateDimensions($v);
        });

        return $validator->validate();
    }

    // -------------------------------------------------------------------------
    // Private methods
    // -------------------------------------------------------------------------

    private function baseRules(): array
    {
        return [
            'src' => ['required', 'string', 'max:1000'],
            'w'   => ['nullable', 'integer', 'min:1', 'max:20000'],
            'h'   => ['nullable', 'integer', 'min:1', 'max:20000'],
            'q'   => ['nullable', 'integer', Rule::in((array) config('imagepresets.allowed_qualities', [80]))],
            'fit' => ['nullable', 'string', Rule::in((array) config('imagepresets.allowed_fits', ['max']))],
            'fm'  => ['nullable', 'string', Rule::in((array) config('imagepresets.allowed_formats', ['webp', 'jpg', 'png', 'gif']))],
        ];
    }

    private function validateSource(\Illuminate\Validation\Validator $validator): void
    {
        $src = (string) ($validator->getData()['src'] ?? '');

        if ($this->normalizer->isRemote($src)) {
            $this->validateRemoteSource($validator, $src);
        } else {
            $this->validateLocalSource($validator, $src);
        }
    }

    private function validateRemoteSource(\Illuminate\Validation\Validator $validator, string $src): void
    {
        $normalized = $this->normalizer->normalize($src);

        if ($normalized === false || !filter_var($normalized, FILTER_VALIDATE_URL)) {
            $validator->errors()->add('src', 'invalid');
            return;
        }

        $host = $this->normalizer->extractHost($normalized);
        if ($host === '') {
            $validator->errors()->add('src', 'invalid');
            return;
        }

        // SSRF protection: block private/reserved IP ranges and localhost
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $validator->errors()->add('src', 'invalid');
                return;
            }
        } elseif (strcasecmp($host, 'localhost') === 0) {
            $validator->errors()->add('src', 'invalid');
            return;
        }

        if (!$this->isHostAllowed($host)) {
            $validator->errors()->add('src', 'not allowed');
        }
    }

    private function validateLocalSource(\Illuminate\Validation\Validator $validator, string $src): void
    {
        if (str_contains($src, '..') || str_contains($src, "\0")) {
            $validator->errors()->add('src', 'invalid');
            return;
        }

        $rel = ltrim(str_replace(['\\', '//'], ['/', '/'], $src), '/');
        if ($rel === '' || $rel[0] === '.') {
            $validator->errors()->add('src', 'invalid');
            return;
        }

        if ($this->sourceResolver->findLocalPath($rel) === null) {
            $validator->errors()->add('src', 'not found');
        }
    }

    private function validateDimensions(\Illuminate\Validation\Validator $validator): void
    {
        $data = $validator->getData();
        $hasW = $this->hasParam($data, 'w');
        $hasH = $this->hasParam($data, 'h');

        if (isset($data['fit']) && !$hasW && !$hasH) {
            $validator->errors()->add('fit', 'requires dimensions');
        }

        if ($hasW && $hasH) {
            if (!$this->isPairAllowed((int) $data['w'], (int) $data['h'])) {
                $validator->errors()->add('w', 'invalid pair');
                $validator->errors()->add('h', 'invalid pair');
            }
        } elseif ($hasW) {
            if (!in_array((int) $data['w'], (array) config('imagepresets.allowed_widths', []), true)) {
                $validator->errors()->add('w', 'not allowed');
            }
        } elseif ($hasH) {
            if (!in_array((int) $data['h'], (array) config('imagepresets.allowed_heights', []), true)) {
                $validator->errors()->add('h', 'not allowed');
            }
        }
    }

    private function hasParam(array $data, string $key): bool
    {
        return ($data[$key] ?? null) !== null && $data[$key] !== '';
    }

    private function isPairAllowed(int $w, int $h): bool
    {
        foreach ((array) config('imagepresets.allowed_sizes', []) as $pair) {
            if (count($pair) === 2 && (int) $pair[0] === $w && (int) $pair[1] === $h) {
                return true;
            }
        }

        return false;
    }

    private function isHostAllowed(string $host): bool
    {
        $host = strtolower($host);

        // The application's own host is always permitted
        $appHost = (string) (parse_url((string) config('app.url', ''), PHP_URL_HOST) ?? '');
        if ($appHost !== '' && $host === strtolower($appHost)) {
            return true;
        }

        foreach ((array) config('imagepresets.allowed_hosts', []) as $allowed) {
            if ($host === strtolower((string) $allowed)) {
                return true;
            }
        }

        return false;
    }
}

