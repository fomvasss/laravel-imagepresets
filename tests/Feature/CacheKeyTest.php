<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Services\ImagepresetService;
use Fomvasss\Imagepresets\Support\GlideProcessor;
use Fomvasss\Imagepresets\Tests\TestCase;
use Fomvasss\Imagepresets\Validation\ImagepresetValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImagepresetService::class)]
final class CacheKeyTest extends TestCase
{
    private function fileName(array $query): string
    {
        $service = app(ImagepresetService::class);
        $validated = (new \ReflectionMethod(ImagepresetService::class, 'applyPreset'))->invoke($service, $query);
        $glideParams = app(GlideProcessor::class)->buildParams($validated);

        return (new \ReflectionMethod(ImagepresetService::class, 'buildPresetFileName'))
            ->invoke($service, Request::create('/imagepreset', 'GET', $query), $glideParams, 'webp');
    }

    public function test_unknown_query_parameters_do_not_create_new_cache_files(): void
    {
        $plain = $this->fileName(['src' => 'a.jpg', 'w' => '300']);

        $this->assertSame($plain, $this->fileName(['src' => 'a.jpg', 'w' => '300', 'x' => '1']));
        $this->assertSame($plain, $this->fileName(['src' => 'a.jpg', 'w' => '300', 'x' => '2']));
    }

    public function test_changed_default_quality_gives_new_cache_file(): void
    {
        $before = $this->fileName(['src' => 'a.jpg', 'w' => '300']);

        config(['imagepresets.quality' => 90]);

        $this->assertNotSame($before, $this->fileName(['src' => 'a.jpg', 'w' => '300']));
    }

    public function test_changed_driver_gives_new_cache_file(): void
    {
        $before = $this->fileName(['src' => 'a.jpg', 'w' => '300']);

        config(['imagepresets.driver' => 'imagick']);

        $this->assertNotSame($before, $this->fileName(['src' => 'a.jpg', 'w' => '300']));
    }

    public function test_changed_preset_gives_new_cache_file(): void
    {
        $before = $this->fileName(['src' => 'a.jpg', 'preset' => 'hero']);

        config(['imagepresets.presets.hero.q' => 90]);

        $this->assertNotSame($before, $this->fileName(['src' => 'a.jpg', 'preset' => 'hero']));
    }

    // An explicit q wins over the config default, so changing the default leaves that file alone
    public function test_explicit_quality_ignores_default_quality(): void
    {
        $before = $this->fileName(['src' => 'a.jpg', 'w' => '300', 'q' => '90']);

        config(['imagepresets.quality' => 70]);

        $this->assertSame($before, $this->fileName(['src' => 'a.jpg', 'w' => '300', 'q' => '90']));
    }

    public function test_quality_wildcard_still_caps_at_100(): void
    {
        config(['imagepresets.allowed_qualities' => ['*']]);

        $this->expectException(ValidationException::class);

        app(ImagepresetValidator::class)->validate(Request::create('/imagepreset', 'GET', ['src' => 'a.jpg', 'q' => '500']));
    }
}
