<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Console\VerifyCommand;
use Fomvasss\Imagepresets\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VerifyCommand::class)]
final class VerifyCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_reports_no_files_when_cache_dir_missing(): void
    {
        $this->artisan('imagepresets:verify')
            ->expectsOutputToContain('does not exist or is already empty')
            ->assertSuccessful();
    }

    public function test_dry_run_lists_corrupted_file_without_deleting_it(): void
    {
        Storage::disk('public')->put('imagepresets/good.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('imagepresets/bad.jpg', substr($this->fakeJpeg(), 0, -50));

        $this->artisan('imagepresets:verify')
            ->expectsOutputToContain('Corrupted: imagepresets/bad.jpg')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('imagepresets/good.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('imagepresets/bad.jpg'));
    }

    public function test_delete_removes_only_the_corrupted_file(): void
    {
        Storage::disk('public')->put('imagepresets/good.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('imagepresets/bad.jpg', substr($this->fakeJpeg(), 0, -50));

        $this->artisan('imagepresets:verify', ['--delete' => true])->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('imagepresets/good.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('imagepresets/bad.jpg'));
    }

    public function test_delete_removes_stale_orphaned_tmp_file(): void
    {
        Storage::disk('public')->put('imagepresets/result.jpg.tmpstale', $this->fakeJpeg());
        touch(Storage::disk('public')->path('imagepresets/result.jpg.tmpstale'), now()->subHours(2)->timestamp);

        $this->artisan('imagepresets:verify', ['--delete' => true])->assertSuccessful();

        $this->assertFalse(Storage::disk('public')->exists('imagepresets/result.jpg.tmpstale'));
    }

    public function test_recent_orphaned_tmp_file_is_kept(): void
    {
        Storage::disk('public')->put('imagepresets/result.jpg.tmpfresh', $this->fakeJpeg());

        $this->artisan('imagepresets:verify', ['--delete' => true])->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('imagepresets/result.jpg.tmpfresh'));
    }

    public function test_deep_lists_suspect_webp_without_deleting_it(): void
    {
        Storage::disk('public')->put('imagepresets/ok.webp', $this->colorfulWebp());
        Storage::disk('public')->put('imagepresets/gray.webp', $this->grayFillerWebp());

        $this->artisan('imagepresets:verify', ['--deep' => true])
            ->expectsOutputToContain('Suspect webp: imagepresets/gray.webp')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('imagepresets/gray.webp'));
    }

    public function test_without_deep_suspect_webp_is_not_reported(): void
    {
        Storage::disk('public')->put('imagepresets/gray.webp', $this->grayFillerWebp());

        $this->artisan('imagepresets:verify')
            ->expectsOutputToContain('Found 0 corrupted file(s)')
            ->assertSuccessful();
    }

    public function test_delete_does_not_touch_suspects(): void
    {
        Storage::disk('public')->put('imagepresets/gray.webp', $this->grayFillerWebp());

        $this->artisan('imagepresets:verify', ['--deep' => true, '--delete' => true])->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('imagepresets/gray.webp'));
    }

    public function test_delete_suspects_removes_only_suspects(): void
    {
        Storage::disk('public')->put('imagepresets/ok.webp', $this->colorfulWebp());
        Storage::disk('public')->put('imagepresets/gray.webp', $this->grayFillerWebp());

        $this->artisan('imagepresets:verify', ['--delete-suspects' => true])->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('imagepresets/ok.webp'));
        $this->assertFalse(Storage::disk('public')->exists('imagepresets/gray.webp'));
    }

    public function test_gray_threshold_option_is_respected(): void
    {
        Storage::disk('public')->put('imagepresets/gray.webp', $this->grayFillerWebp());

        $this->artisan('imagepresets:verify', ['--deep' => true, '--gray-threshold' => 60])
            ->expectsOutputToContain('Found 0 suspect webp file(s)')
            ->assertSuccessful();
    }

    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    private function colorfulWebp(): string
    {
        $img = imagecreatetruecolor(100, 100);
        for ($y = 0; $y < 100; $y++) {
            $c = imagecolorallocate($img, (int) ($y * 255 / 100), 30, 255 - (int) ($y * 255 / 100));
            imagefilledrectangle($img, 0, $y, 99, $y, $c);
        }
        ob_start();
        imagewebp($img, null, 80);
        imagedestroy($img);

        return ob_get_clean();
    }

    // Нижня половина — точний rgb(128,128,128): та сама заливка, яку libwebp лишає
    // замість недекодованих macroblock-рядків пошкодженого VP8-потоку
    private function grayFillerWebp(): string
    {
        $img = imagecreatetruecolor(100, 100);
        imagefilledrectangle($img, 0, 0, 99, 49, imagecolorallocate($img, 40, 80, 200));
        imagefilledrectangle($img, 0, 50, 99, 99, imagecolorallocate($img, 128, 128, 128));
        ob_start();
        imagewebp($img, null, 80);
        imagedestroy($img);

        return ob_get_clean();
    }
}
