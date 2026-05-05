<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\ImagepresetServiceProvider;
use Fomvasss\Imagepresets\Services\ImagepresetService;
use Fomvasss\Imagepresets\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * HTTP-level tests for optional Signed URL route protection.
 *
 * A separate test class is required because route middleware is resolved
 * at application boot time (before setUp() runs), so signed=true must be
 * set in defineEnvironment() to take effect on route registration.
 */
#[CoversClass(ImagepresetService::class)]
final class SignedUrlRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Enable signed URL for all tests in this class.
        $app['config']->set('imagepresets.route.signed', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // -------------------------------------------------------------------------
    // Valid signed URL
    // -------------------------------------------------------------------------

    public function test_valid_signed_url_is_not_rejected_with_403(): void
    {
        Storage::disk('public')->put('photo.jpg', $this->fakeJpeg());

        $url = imagepreset_url('photo.jpg', ['w' => 300]);

        // Signature is valid — must not return 403.
        $this->assertNotEquals(403, $this->get($url)->status());
    }

    // -------------------------------------------------------------------------
    // Unsigned request
    // -------------------------------------------------------------------------

    public function test_unsigned_request_returns_403(): void
    {
        // Plain URL without signature parameter.
        $plainUrl = route('imagepresets', ['src' => 'photo.jpg', 'w' => 300]);

        $this->get($plainUrl)->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Tampered signed URL
    // -------------------------------------------------------------------------

    public function test_tampered_param_returns_403(): void
    {
        $signedUrl = imagepreset_url('photo.jpg', ['w' => 300]);

        // Replace w=300 with w=9999 — signature no longer matches.
        $tamperedUrl = str_replace('w=300', 'w=9999', $signedUrl);

        $this->get($tamperedUrl)->assertStatus(403);
    }

    public function test_tampered_src_returns_403(): void
    {
        $signedUrl = imagepreset_url('photo.jpg', ['w' => 300]);

        $tamperedUrl = str_replace('src=photo.jpg', 'src=other.jpg', $signedUrl);

        $this->get($tamperedUrl)->assertStatus(403);
    }

    public function test_removed_signature_returns_403(): void
    {
        $signedUrl = imagepreset_url('photo.jpg', ['w' => 300]);

        // Strip the signature param entirely.
        $urlWithoutSignature = preg_replace('/[&?]signature=[^&]+/', '', $signedUrl);

        $this->get((string) $urlWithoutSignature)->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}

