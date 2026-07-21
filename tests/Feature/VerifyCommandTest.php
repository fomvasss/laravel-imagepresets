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

    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);

        return ob_get_clean();
    }
}
