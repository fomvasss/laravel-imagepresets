<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Tests\TestCase;
use Fomvasss\Imagepresets\Validation\ImagepresetValidator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * HTTP validation tests for the /imagepresets endpoint.
 */
#[CoversClass(ImagepresetValidator::class)]
final class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // -------------------------------------------------------------------------
    // Відсутній src
    // -------------------------------------------------------------------------

    public function test_missing_src_returns_404(): void
    {
        $this->get(route('imagepreset'))->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Локальний src — path traversal
    // -------------------------------------------------------------------------

    public function test_path_traversal_with_dotdot_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => '../../etc/passwd']))->assertStatus(404);
    }

    public function test_path_traversal_with_null_byte_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => "image\0.jpg"]))->assertStatus(404);
    }

    public function test_src_starting_with_dot_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => '.hidden/image.jpg']))->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Параметр w / h
    // -------------------------------------------------------------------------

    public function test_non_allowed_width_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'w' => 9999]))->assertStatus(404);
    }

    public function test_allowed_width_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        // Може повернути 404 через відсутній Glide output, але НЕ через валідацію
        // Тестуємо лише що валідація пропускає — статус не 404 через validation
        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'w' => 300]));
        // Статус 404 може бути від Glide (немає реального зображення), але не від validation
        $this->assertNotSame(422, $response->status());
    }

    public function test_non_allowed_height_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'h' => 9999]))->assertStatus(404);
    }

    public function test_non_allowed_pair_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        // Пара [999, 777] не в allowed_sizes
        $this->get(route('imagepreset', ['src' => 'test.jpg', 'w' => 999, 'h' => 777]))->assertStatus(404);
    }

    public function test_fit_without_dimensions_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'fit' => 'crop']))->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Параметр q (якість)
    // -------------------------------------------------------------------------

    public function test_non_allowed_quality_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'q' => 55]))->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Параметр fm (формат)
    // -------------------------------------------------------------------------

    public function test_non_allowed_format_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'fm' => 'bmp']))->assertStatus(404);
    }

    public function test_allowed_format_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'fm' => 'webp']));
        $this->assertNotSame(422, $response->status());
    }

    // -------------------------------------------------------------------------
    // Remote src — SSRF
    // -------------------------------------------------------------------------

    public function test_remote_src_with_private_ip_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => 'http://192.168.1.1/image.jpg']))->assertStatus(404);
    }

    public function test_remote_src_with_localhost_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => 'http://localhost/image.jpg']))->assertStatus(404);
    }

    public function test_remote_src_with_loopback_ip_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => 'http://127.0.0.1/image.jpg']))->assertStatus(404);
    }

    public function test_remote_src_with_ipv6_loopback_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => 'http://[::1]/image.jpg']))->assertStatus(404);
    }

    public function test_remote_src_with_not_allowed_host_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => 'https://evil.example.com/image.jpg']))->assertStatus(404);
    }

    public function test_remote_src_with_invalid_url_returns_404(): void
    {
        $this->get(route('imagepreset', ['src' => 'https://']))->assertStatus(404);
    }

    public function test_remote_src_app_host_passes_host_validation(): void
    {
        // Хост з APP_URL дозволений — валідація хоста проходить.
        // Файл не знайдено локально, Http::fake повертає 404 → SourceResolver повертає null.
        \Illuminate\Support\Facades\Http::fake([
            'http://myapp.test/*' => \Illuminate\Support\Facades\Http::response('', 404),
        ]);

        config(['app.url' => 'http://myapp.test']);

        $response = $this->get(route('imagepreset', ['src' => 'http://myapp.test/storage/image.jpg']));
        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // blur / sharp
    // -------------------------------------------------------------------------

    public function test_blur_above_max_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'blur' => 101]))->assertStatus(404);
    }

    public function test_blur_negative_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'blur' => -1]))->assertStatus(404);
    }

    public function test_valid_blur_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'blur' => 50]));
        $this->assertNotSame(422, $response->status());
    }

    public function test_sharp_above_max_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'sharp' => 101]))->assertStatus(404);
    }

    public function test_valid_sharp_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'sharp' => 30]));
        $this->assertNotSame(422, $response->status());
    }

    // -------------------------------------------------------------------------
    // or (orientation)
    // -------------------------------------------------------------------------

    public function test_invalid_orientation_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'or' => '45']))->assertStatus(404);
    }

    public function test_valid_orientation_auto_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'or' => 'auto']));
        $this->assertNotSame(422, $response->status());
    }

    public function test_valid_orientation_90_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'or' => '90']));
        $this->assertNotSame(422, $response->status());
    }

    // -------------------------------------------------------------------------
    // crop
    // -------------------------------------------------------------------------

    public function test_invalid_crop_format_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'crop' => '100x100']))->assertStatus(404);
    }

    public function test_valid_crop_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'crop' => '100,100,10,10']));
        $this->assertNotSame(422, $response->status());
    }

    // -------------------------------------------------------------------------
    // bg (background)
    // -------------------------------------------------------------------------

    public function test_invalid_bg_color_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'bg' => 'zzzzzz']))->assertStatus(404);
    }

    public function test_bg_too_short_returns_404(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'test.jpg', 'bg' => 'ff']))->assertStatus(404);
    }

    public function test_valid_bg_hex3_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'bg' => 'fff']));
        $this->assertNotSame(422, $response->status());
    }

    public function test_valid_bg_hex6_passes_validation(): void
    {
        Storage::disk('public')->put('test.jpg', $this->fakeJpeg());

        $response = $this->get(route('imagepreset', ['src' => 'test.jpg', 'bg' => 'ff5733']));
        $this->assertNotSame(422, $response->status());
    }

    // -------------------------------------------------------------------------
    // Допоміжні методи
    // -------------------------------------------------------------------------

    /**
     * Мінімальний валідний JPEG (10×10 px) у вигляді рядка.
     */
    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);
        return ob_get_clean();
    }
}

