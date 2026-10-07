<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Console\ClearCommand;
use Fomvasss\Imagepresets\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClearCommand::class)]
final class ClearCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::disk('public')->put('imagepresets/thumb.webp', 'x');
        Storage::disk('public')->put('uploads/photo.jpg', 'x');
    }

    public function test_clears_only_the_cache_directory(): void
    {
        $this->artisan('imagepresets:clear')->assertSuccessful();

        $this->assertFalse(Storage::disk('public')->exists('imagepresets/thumb.webp'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/photo.jpg'));
    }

    public function test_refuses_empty_path(): void
    {
        config(['imagepresets.path' => '']);

        $this->artisan('imagepresets:clear')
            ->expectsOutputToContain('path is empty')
            ->assertFailed();

        $this->assertTrue(Storage::disk('public')->exists('imagepresets/thumb.webp'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/photo.jpg'));
    }

    public function test_refuses_paths_that_normalize_to_the_disk_root(): void
    {
        foreach (['.', './', 'x/..', '../x'] as $path) {
            $this->artisan('imagepresets:clear', ['--path' => $path])
                ->expectsOutputToContain('path is empty')
                ->assertFailed();
        }

        config(['imagepresets.path' => './']);
        $this->artisan('imagepresets:clear')->assertFailed();

        $this->assertTrue(Storage::disk('public')->exists('uploads/photo.jpg'));
    }

    public function test_refuses_root_path_option(): void
    {
        $this->artisan('imagepresets:clear', ['--path' => '/'])->assertFailed();

        $this->assertTrue(Storage::disk('public')->exists('uploads/photo.jpg'));
    }
}
