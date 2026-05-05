<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Services\ImagepresetService;
use Fomvasss\Imagepresets\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * URL generation tests for optional Signed URL support.
 * Route-level (HTTP) tests are in SignedUrlRouteTest.
 */
#[CoversClass(ImagepresetService::class)]
final class SignedUrlTest extends TestCase
{
    // -------------------------------------------------------------------------
    // URL generation — signed disabled
    // -------------------------------------------------------------------------

    public function test_url_without_signed_does_not_contain_signature(): void
    {
        $this->app['config']->set('imagepresets.route.signed', false);

        $url = imagepreset_url('photo.jpg', ['w' => 300]);

        $this->assertStringNotContainsString('signature=', $url);
    }

    // -------------------------------------------------------------------------
    // URL generation — signed enabled
    // -------------------------------------------------------------------------

    public function test_url_with_signed_contains_signature(): void
    {
        $this->app['config']->set('imagepresets.route.signed', true);

        $url = imagepreset_url('photo.jpg', ['w' => 300]);

        $this->assertStringContainsString('signature=', $url);
    }

    public function test_signed_url_is_permanent_no_expires_param(): void
    {
        $this->app['config']->set('imagepresets.route.signed', true);

        $url = imagepreset_url('photo.jpg', ['w' => 300]);

        $this->assertStringNotContainsString('expires=', $url);
    }

    public function test_facade_url_with_signed_contains_signature(): void
    {
        $this->app['config']->set('imagepresets.route.signed', true);

        $url = \Fomvasss\Imagepresets\Facades\Imagepreset::url('photo.jpg', ['w' => 300]);

        $this->assertStringContainsString('signature=', $url);
    }

    public function test_named_preset_signed_url_contains_signature(): void
    {
        $this->app['config']->set('imagepresets.route.signed', true);

        $url = imagepreset_url('photo.jpg', 'thumb');

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('preset=thumb', $url);
    }

    public function test_same_params_always_produce_same_signature(): void
    {
        $this->app['config']->set('imagepresets.route.signed', true);

        $url1 = imagepreset_url('photo.jpg', ['w' => 300]);
        $url2 = imagepreset_url('photo.jpg', ['w' => 300]);

        $this->assertSame($url1, $url2);
    }

    public function test_different_params_produce_different_signatures(): void
    {
        $this->app['config']->set('imagepresets.route.signed', true);

        $url1 = imagepreset_url('photo.jpg', ['w' => 300]);
        $url2 = imagepreset_url('photo.jpg', ['w' => 600]);

        $this->assertNotSame($url1, $url2);

        // Extract signatures
        parse_str((string) parse_url($url1, PHP_URL_QUERY), $q1);
        parse_str((string) parse_url($url2, PHP_URL_QUERY), $q2);
        $this->assertNotSame($q1['signature'], $q2['signature']);
    }
}





