<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Unit;

use Fomvasss\Imagepresets\Support\WebpDecodeCheck;
use Fomvasss\Imagepresets\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebpDecodeCheck::class)]
final class WebpDecodeCheckTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/webpdecodecheck_test_'.uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_colorful_webp_passes(): void
    {
        $path = $this->writeWebp('colorful.webp', $this->colorfulImage());

        $this->assertNull(WebpDecodeCheck::isSuspicious($path));
    }

    public function test_gray_filler_above_threshold_is_flagged(): void
    {
        // Нижня половина — точний rgb(128,128,128): та сама заливка, яку libwebp
        // лишає замість недекодованих macroblock-рядків пошкодженого VP8-потоку
        $path = $this->writeWebp('gray-tail.webp', $this->halfGrayImage());

        $reason = WebpDecodeCheck::isSuspicious($path);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('rgb(128,128,128)', $reason);
    }

    public function test_gray_share_below_threshold_passes(): void
    {
        $path = $this->writeWebp('gray-tail.webp', $this->halfGrayImage());

        $this->assertNull(WebpDecodeCheck::isSuspicious($path, 0.60));
    }

    public function test_undecodable_webp_is_flagged(): void
    {
        // Валідний RIFF-контейнер (розмір збігається), але VP8-payload — сміття
        $payload = str_repeat("\x00", 100);
        $data = 'RIFF'.pack('V', 4 + 8 + strlen($payload)).'WEBP'.'VP8 '.pack('V', strlen($payload)).$payload;
        $path = $this->dir.'/garbage.webp';
        file_put_contents($path, $data);

        $this->assertSame('webp payload does not decode', WebpDecodeCheck::isSuspicious($path));
    }

    public function test_missing_file_is_flagged(): void
    {
        $this->assertSame('webp payload does not decode', WebpDecodeCheck::isSuspicious($this->dir.'/does-not-exist.webp'));
    }

    public function test_tiny_image_is_skipped(): void
    {
        $img = imagecreatetruecolor(4, 4);
        imagefilledrectangle($img, 0, 0, 3, 3, imagecolorallocate($img, 128, 128, 128));
        $path = $this->writeWebp('tiny.webp', $img);

        $this->assertNull(WebpDecodeCheck::isSuspicious($path));
    }

    // -------------------------------------------------------------------------
    // Допоміжні методи
    // -------------------------------------------------------------------------

    private function writeWebp(string $name, \GdImage $img): string
    {
        $path = $this->dir.'/'.$name;
        imagewebp($img, $path, 80);
        imagedestroy($img);

        return $path;
    }

    private function colorfulImage(): \GdImage
    {
        $img = imagecreatetruecolor(200, 200);
        for ($y = 0; $y < 200; $y++) {
            $c = imagecolorallocate($img, (int) ($y * 255 / 200), 30, 255 - (int) ($y * 255 / 200));
            imagefilledrectangle($img, 0, $y, 199, $y, $c);
        }

        return $img;
    }

    private function halfGrayImage(): \GdImage
    {
        $img = imagecreatetruecolor(200, 200);
        imagefilledrectangle($img, 0, 0, 199, 99, imagecolorallocate($img, 40, 80, 200));
        imagefilledrectangle($img, 0, 100, 199, 199, imagecolorallocate($img, 128, 128, 128));

        return $img;
    }
}
