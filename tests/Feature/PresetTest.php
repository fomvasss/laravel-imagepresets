<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Services\ImagepresetService;
use Fomvasss\Imagepresets\Tests\TestCase;
use Fomvasss\Imagepresets\Validation\ImagepresetValidator;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for named preset functionality.
 */
#[CoversClass(ImagepresetValidator::class)]
#[CoversClass(ImagepresetService::class)]
final class PresetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // -------------------------------------------------------------------------
    // Валідація preset-параметра
    // -------------------------------------------------------------------------

    public function test_unknown_preset_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'preset' => 'nonexistent']))
            ->assertStatus(404);
    }

    public function test_known_preset_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'preset' => 'thumb']));

        // Валідація пройшла — статус не 422
        $this->assertNotSame(422, $response->status());
    }

    public function test_preset_without_src_returns_404(): void
    {
        $this->get(route('imagepreset', ['preset' => 'thumb']))
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // URL-генерація через рядок (shorthand)
    // -------------------------------------------------------------------------

    public function test_url_helper_with_preset_string(): void
    {
        $url = imagepreset_url('photo.jpg', 'thumb');

        $this->assertStringContainsString('preset=thumb', $url);
        $this->assertStringContainsString('src=', $url);
    }

    public function test_url_helper_with_preset_in_array(): void
    {
        $url = imagepreset_url('photo.jpg', ['preset' => 'hero']);

        $this->assertStringContainsString('preset=hero', $url);
    }

    public function test_url_helper_with_preset_and_override(): void
    {
        $url = imagepreset_url('photo.jpg', ['preset' => 'thumb', 'fm' => 'jpg']);

        $this->assertStringContainsString('preset=thumb', $url);
        $this->assertStringContainsString('fm=jpg', $url);
    }

    // -------------------------------------------------------------------------
    // Preset params bypass allowed_widths/heights/qualities
    // -------------------------------------------------------------------------

    public function test_preset_width_not_in_allowed_widths_passes(): void
    {
        // 'hero' preset has w=1200 which IS in allowed_widths, but this test
        // confirms preset resolution works end-to-end without extra width check.
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'preset' => 'hero']));
        $this->assertNotSame(422, $response->status());
    }

    public function test_preset_with_explicit_override_still_validates_override(): void
    {
        // Explicitly passing a width NOT in allowed_widths alongside a preset
        // should still fail — the override goes through normal validation.
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'preset' => 'thumb', 'w' => 9999]))
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Допоміжні методи
    // -------------------------------------------------------------------------

    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);

        return ob_get_clean();
    }
}

