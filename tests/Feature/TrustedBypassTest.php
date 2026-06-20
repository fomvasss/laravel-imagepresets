<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Services\ImagepresetService;
use Fomvasss\Imagepresets\Tests\TestCase;
use Fomvasss\Imagepresets\Validation\ImagepresetValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the trusted bypass feature (IMAGEPRESET_TRUSTED_BYPASS).
 *
 * Trusted bypass allows backend-generated URLs (Blade / helper / facade) to skip
 * allowed_widths / allowed_heights / allowed_sizes / allowed_qualities /
 * allowed_fits / allowed_formats checks via a server-signed _t token.
 * Basic security validation (path traversal, remote hosts, max:20000) is always enforced.
 */
#[CoversClass(ImagepresetValidator::class)]
#[CoversClass(ImagepresetService::class)]
final class TrustedBypassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->app['config']->set('imagepresets.trusted_bypass', true);
    }

    // -------------------------------------------------------------------------
    // URL generation
    // -------------------------------------------------------------------------

    public function test_url_with_bypass_true_and_config_enabled_contains_t_token(): void
    {
        $url = imagepreset_url('photo.jpg', ['w' => 756], true);

        $this->assertStringContainsString('_t=', $url);
    }

    public function test_url_with_bypass_false_does_not_contain_t_token(): void
    {
        $url = imagepreset_url('photo.jpg', ['w' => 756]);

        $this->assertStringNotContainsString('_t=', $url);
    }

    public function test_url_with_bypass_true_but_config_disabled_does_not_contain_t_token(): void
    {
        $this->app['config']->set('imagepresets.trusted_bypass', false);

        $url = imagepreset_url('photo.jpg', ['w' => 756], true);

        $this->assertStringNotContainsString('_t=', $url);
    }

    public function test_facade_with_bypass_contains_t_token(): void
    {
        $url = \Fomvasss\Imagepresets\Facades\Imagepreset::url('photo.jpg', ['w' => 756], true);

        $this->assertStringContainsString('_t=', $url);
    }

    public function test_same_params_always_produce_same_token(): void
    {
        $url1 = imagepreset_url('photo.jpg', ['w' => 756], true);
        $url2 = imagepreset_url('photo.jpg', ['w' => 756], true);

        parse_str((string) parse_url($url1, PHP_URL_QUERY), $q1);
        parse_str((string) parse_url($url2, PHP_URL_QUERY), $q2);

        $this->assertSame($q1['_t'], $q2['_t']);
    }

    public function test_different_params_produce_different_tokens(): void
    {
        $url1 = imagepreset_url('photo.jpg', ['w' => 756], true);
        $url2 = imagepreset_url('photo.jpg', ['w' => 800], true);

        parse_str((string) parse_url($url1, PHP_URL_QUERY), $q1);
        parse_str((string) parse_url($url2, PHP_URL_QUERY), $q2);

        $this->assertNotSame($q1['_t'], $q2['_t']);
    }

    // -------------------------------------------------------------------------
    // Allowlist bypass — direct validator tests (more precise than HTTP)
    // -------------------------------------------------------------------------

    public function test_validator_allows_non_allowed_width_with_valid_token(): void
    {
        // w=756 is not in allowed_widths=[100,300,600,1200]
        $validated = $this->validateWithToken(['w' => 756, 'src' => 'photo.jpg']);

        $this->assertSame('756', $validated['w']);
    }

    public function test_validator_allows_non_allowed_pair_with_valid_token(): void
    {
        // [756,380] is not in allowed_sizes
        $validated = $this->validateWithToken(['w' => 756, 'h' => 380, 'src' => 'photo.jpg']);

        $this->assertSame('756', $validated['w']);
        $this->assertSame('380', $validated['h']);
    }

    public function test_validator_allows_non_allowed_quality_with_valid_token(): void
    {
        // q=75 is not in allowed_qualities=[80,90]
        $validated = $this->validateWithToken(['w' => 300, 'q' => 75, 'src' => 'photo.jpg']);

        $this->assertSame('75', $validated['q']);
    }

    public function test_validator_allows_non_allowed_format_with_valid_token(): void
    {
        // avif is not in allowed_formats
        $validated = $this->validateWithToken(['w' => 300, 'fm' => 'avif', 'src' => 'photo.jpg']);

        $this->assertSame('avif', $validated['fm']);
    }

    public function test_validator_allows_non_allowed_fit_with_valid_token(): void
    {
        // fill-max is not in allowed_fits=['max','fill','crop']
        $validated = $this->validateWithToken(['w' => 300, 'h' => 200, 'fit' => 'fill-max', 'src' => 'photo.jpg']);

        $this->assertSame('fill-max', $validated['fit']);
    }

    // -------------------------------------------------------------------------
    // Fallback to normal validation on invalid / absent token
    // -------------------------------------------------------------------------

    public function test_non_allowed_width_without_token_returns_404(): void
    {
        Storage::disk('public')->put('photo.jpg', $this->fakeJpeg());

        $this->get(route('imagepreset', ['src' => 'photo.jpg', 'w' => 756]))
            ->assertStatus(404);
    }

    public function test_tampered_token_falls_back_to_normal_validation(): void
    {
        Storage::disk('public')->put('photo.jpg', $this->fakeJpeg());

        $url = imagepreset_url('photo.jpg', ['w' => 756], true);
        $tampered = preg_replace('/_t=[^&]+/', '_t=0000000000000000', $url);

        // Token invalid → allowlist rejects w=756 → 404
        $this->get((string) $tampered)->assertStatus(404);
    }

    public function test_tampered_param_invalidates_token(): void
    {
        Storage::disk('public')->put('photo.jpg', $this->fakeJpeg());

        $url = imagepreset_url('photo.jpg', ['w' => 756], true);
        $tampered = str_replace('w=756', 'w=9999', $url);

        // w param changed — token mismatch → normal validation → w=9999 not allowed → 404
        $this->get((string) $tampered)->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Base rules still enforced for trusted requests
    // -------------------------------------------------------------------------

    public function test_trusted_token_does_not_bypass_max_width_limit(): void
    {
        Storage::disk('public')->put('photo.jpg', $this->fakeJpeg());

        // w=25000 exceeds the hard max:20000 base rule
        $url = imagepreset_url('photo.jpg', ['w' => 25000], true);

        $this->get($url)->assertStatus(404);
    }

    public function test_trusted_token_does_not_bypass_max_width_limit_at_validator_level(): void
    {
        $this->expectException(ValidationException::class);

        $this->validateWithToken(['w' => 25000, 'src' => 'photo.jpg']);
    }

    public function test_trusted_token_does_not_bypass_path_traversal_check(): void
    {
        $url = imagepreset_url('../../etc/passwd', ['w' => 756], true);

        $this->get($url)->assertStatus(404);
    }

    public function test_fit_without_dimensions_still_fails_with_trusted_token(): void
    {
        $this->expectException(ValidationException::class);

        // fit requires at least one dimension — even with bypass
        $this->validateWithToken(['fit' => 'crop', 'src' => 'photo.jpg']);
    }

    // -------------------------------------------------------------------------
    // Config disabled — bypass param has no effect
    // -------------------------------------------------------------------------

    public function test_bypass_has_no_effect_when_config_disabled(): void
    {
        $this->app['config']->set('imagepresets.trusted_bypass', false);
        Storage::disk('public')->put('photo.jpg', $this->fakeJpeg());

        $url = imagepreset_url('photo.jpg', ['w' => 756], true);

        $this->assertStringNotContainsString('_t=', $url);
        $this->get($url)->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Cache key: _t must not create duplicate cache entries (SVG — works with fake disk)
    // -------------------------------------------------------------------------

    public function test_trusted_and_plain_requests_share_svg_cache(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>';
        Storage::disk('public')->put('logo.svg', $svg);

        // First request with _t — generates SVG cache
        $trustedUrl = imagepreset_url('logo.svg', [], true);
        $this->get($trustedUrl)->assertSuccessful();

        // Second request without _t for the same src — must hit same cache (not regenerate)
        $plainUrl = route('imagepreset', ['src' => 'logo.svg']);
        $this->get($plainUrl)->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generates a valid trusted URL for the given params, extracts query string,
     * builds a Request and calls the validator. Returns validated data.
     *
     * @throws ValidationException
     */
    private function validateWithToken(array $params): array
    {
        $src = (string) ($params['src'] ?? 'photo.jpg');
        unset($params['src']);

        $url = imagepreset_url($src, $params, true);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $request = Request::create(route('imagepreset'), 'GET', $query);

        return app(ImagepresetValidator::class)->validate($request);
    }

    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
