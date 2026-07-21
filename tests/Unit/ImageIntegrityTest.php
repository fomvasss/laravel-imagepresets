<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Unit;

use Fomvasss\Imagepresets\Support\ImageIntegrity;
use Fomvasss\Imagepresets\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImageIntegrity::class)]
final class ImageIntegrityTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/imageintegrity_test_'.uniqid('', true);
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

    // -------------------------------------------------------------------------
    // jpg
    // -------------------------------------------------------------------------

    public function test_valid_jpeg_passes(): void
    {
        $path = $this->write('valid.jpg', $this->fakeJpeg());

        $this->assertTrue(ImageIntegrity::isValid($path, 'jpg'));
    }

    public function test_truncated_jpeg_fails(): void
    {
        $path = $this->write('truncated.jpg', $this->truncate($this->fakeJpeg()));

        $this->assertFalse(ImageIntegrity::isValid($path, 'jpg'));
    }

    // -------------------------------------------------------------------------
    // png
    // -------------------------------------------------------------------------

    public function test_valid_png_passes(): void
    {
        $path = $this->write('valid.png', $this->fakePng());

        $this->assertTrue(ImageIntegrity::isValid($path, 'png'));
    }

    public function test_truncated_png_fails(): void
    {
        $path = $this->write('truncated.png', $this->truncate($this->fakePng()));

        $this->assertFalse(ImageIntegrity::isValid($path, 'png'));
    }

    // -------------------------------------------------------------------------
    // gif
    // -------------------------------------------------------------------------

    public function test_valid_gif_passes(): void
    {
        $path = $this->write('valid.gif', $this->fakeGif());

        $this->assertTrue(ImageIntegrity::isValid($path, 'gif'));
    }

    public function test_truncated_gif_fails(): void
    {
        $path = $this->write('truncated.gif', $this->truncate($this->fakeGif()));

        $this->assertFalse(ImageIntegrity::isValid($path, 'gif'));
    }

    // -------------------------------------------------------------------------
    // webp
    // -------------------------------------------------------------------------

    public function test_valid_webp_passes(): void
    {
        $path = $this->write('valid.webp', $this->fakeWebp());

        $this->assertTrue(ImageIntegrity::isValid($path, 'webp'));
    }

    public function test_truncated_webp_fails(): void
    {
        $path = $this->write('truncated.webp', $this->truncate($this->fakeWebp()));

        $this->assertFalse(ImageIntegrity::isValid($path, 'webp'));
    }

    // -------------------------------------------------------------------------
    // Загальні edge-cases
    // -------------------------------------------------------------------------

    public function test_empty_file_fails(): void
    {
        $path = $this->write('empty.jpg', '');

        $this->assertFalse(ImageIntegrity::isValid($path, 'jpg'));
    }

    public function test_missing_file_fails(): void
    {
        $this->assertFalse(ImageIntegrity::isValid($this->dir.'/does-not-exist.jpg', 'jpg'));
    }

    // -------------------------------------------------------------------------
    // Допоміжні методи
    // -------------------------------------------------------------------------

    private function write(string $name, string $contents): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function truncate(string $contents): string
    {
        return substr($contents, 0, -50);
    }

    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    private function fakePng(): string
    {
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    private function fakeGif(): string
    {
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagegif($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    private function fakeWebp(): string
    {
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagewebp($img);
        imagedestroy($img);

        return ob_get_clean();
    }
}
