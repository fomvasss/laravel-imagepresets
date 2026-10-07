<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Services\ImagepresetService;
use Fomvasss\Imagepresets\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImagepresetService::class)]
final class CacheReuseTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_cached_remote_source_is_not_downloaded_again(): void
    {
        config(['imagepresets.allowed_hosts' => ['cdn.example.com']]);
        Http::fake(['https://cdn.example.com/*' => Http::response(self::SVG, 200, ['Content-Type' => 'image/svg+xml'])]);

        array_map('unlink', glob(config('imagepresets.source_dir').'/dl_*') ?: []);
        $url = route('imagepreset', ['src' => 'https://cdn.example.com/logo.svg']);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        Http::assertSentCount(1);
        $this->assertSame([], glob(config('imagepresets.source_dir').'/dl_*') ?: []);
    }

    public function test_remote_svg_without_svg_extension_is_served_as_svg(): void
    {
        config(['imagepresets.allowed_hosts' => ['cdn.example.com']]);
        Http::fake(['https://cdn.example.com/*' => Http::response(self::SVG, 200, ['Content-Type' => 'image/svg+xml'])]);

        $this->get(route('imagepreset', ['src' => 'https://cdn.example.com/logo?id=7']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_lock_timeout_does_not_release_the_other_requests_lock(): void
    {
        Storage::disk('public')->put('logo.svg', self::SVG);
        $lock = Cache::lock('imagepreset:'.md5('logo.svg').'.svg', 30);
        $this->assertTrue($lock->get());

        try {
            $this->get(route('imagepreset', ['src' => 'logo.svg']))->assertStatus(503);
        } finally {
            $this->assertFalse(Cache::lock('imagepreset:'.md5('logo.svg').'.svg', 30)->get(), 'the held lock was released by the timed-out request');
            $lock->release();
        }
    }
}
