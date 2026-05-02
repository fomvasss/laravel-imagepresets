<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Support\ResponseBuilder;
use Fomvasss\Imagepresets\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

/** HTTP response headers tests. */
#[CoversClass(ResponseBuilder::class)]
final class ResponseHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_svg_response_has_correct_content_type(): void
    {
        $this->placeSvg('logo.svg');

        $response = $this->get(route('imagepresets', ['src' => 'logo.svg']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_svg_response_has_security_headers(): void
    {
        $this->placeSvg('logo.svg');

        $response = $this->get(route('imagepresets', ['src' => 'logo.svg']));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Content-Disposition', 'inline');
    }

    public function test_svg_response_has_cache_headers(): void
    {
        $this->placeSvg('logo.svg');

        $response = $this->get(route('imagepresets', ['src' => 'logo.svg']));

        $response->assertHeader('Cache-Control');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('s-maxage', $cacheControl);
    }

    public function test_svg_response_has_etag_and_last_modified(): void
    {
        $this->placeSvg('logo.svg');

        $response = $this->get(route('imagepresets', ['src' => 'logo.svg']));

        $response->assertHeader('ETag');
        $response->assertHeader('Last-Modified');
    }

    public function test_svg_csp_header_disallows_scripts(): void
    {
        $this->placeSvg('logo.svg');

        $response = $this->get(route('imagepresets', ['src' => 'logo.svg']));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", (string) $csp);
        $this->assertStringContainsString('sandbox', (string) $csp);
    }

    // -------------------------------------------------------------------------
    // Допоміжні методи
    // -------------------------------------------------------------------------

    private function placeSvg(string $filename): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>';
        Storage::disk('public')->put($filename, $svg);
    }
}

