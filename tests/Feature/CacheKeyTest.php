<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Services\ImagepresetService;
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
        $method = new \ReflectionMethod(ImagepresetService::class, 'buildPresetFileName');

        return $method->invoke(app(ImagepresetService::class), Request::create('/imagepreset', 'GET', $query), 'webp');
    }

    public function test_unknown_query_parameters_do_not_create_new_cache_files(): void
    {
        $plain = $this->fileName(['src' => 'a.jpg', 'w' => '300']);

        $this->assertSame($plain, $this->fileName(['src' => 'a.jpg', 'w' => '300', 'x' => '1']));
        $this->assertSame($plain, $this->fileName(['src' => 'a.jpg', 'w' => '300', 'x' => '2']));
        // the key of a URL without extra parameters is unchanged, so existing caches stay valid
        $this->assertSame(md5('{"src":"a.jpg","w":"300"}').'.webp', $plain);
    }

    public function test_quality_wildcard_still_caps_at_100(): void
    {
        config(['imagepresets.allowed_qualities' => ['*']]);

        $this->expectException(ValidationException::class);

        app(ImagepresetValidator::class)->validate(Request::create('/imagepreset', 'GET', ['src' => 'a.jpg', 'q' => '500']));
    }
}
