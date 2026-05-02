<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Unit;

use Fomvasss\Imagepresets\Support\GlideProcessor;
use Fomvasss\Imagepresets\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GlideProcessor::class)]
final class GlideProcessorTest extends TestCase
{
    private GlideProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new GlideProcessor();
    }

    // -------------------------------------------------------------------------
    // buildParams() — без розмірів
    // -------------------------------------------------------------------------

    public function test_build_params_without_dimensions_uses_defaults(): void
    {
        $params = $this->processor->buildParams([]);

        $this->assertSame('80', $params['q']);
        $this->assertSame('webp', $params['fm']);
        $this->assertArrayNotHasKey('w', $params);
        $this->assertArrayNotHasKey('h', $params);
        $this->assertArrayNotHasKey('fit', $params);
    }

    public function test_build_params_uses_explicit_quality(): void
    {
        $params = $this->processor->buildParams(['q' => '90']);

        $this->assertSame('90', $params['q']);
    }

    public function test_build_params_uses_explicit_format(): void
    {
        $params = $this->processor->buildParams(['fm' => 'png']);

        $this->assertSame('png', $params['fm']);
    }

    // -------------------------------------------------------------------------
    // buildParams() — з розмірами
    // -------------------------------------------------------------------------

    public function test_build_params_with_width_only_sets_default_fit_one(): void
    {
        $params = $this->processor->buildParams(['w' => '300']);

        $this->assertSame('300', $params['w']);
        $this->assertSame('max', $params['fit']); // default_fit_one
        $this->assertArrayNotHasKey('h', $params);
    }

    public function test_build_params_with_height_only_sets_default_fit_one(): void
    {
        $params = $this->processor->buildParams(['h' => '200']);

        $this->assertSame('200', $params['h']);
        $this->assertSame('max', $params['fit']);
    }

    public function test_build_params_with_both_dimensions_sets_default_fit_both(): void
    {
        $params = $this->processor->buildParams(['w' => '300', 'h' => '200']);

        $this->assertSame('300', $params['w']);
        $this->assertSame('200', $params['h']);
        $this->assertSame('fill', $params['fit']); // default_fit_both
    }

    public function test_build_params_explicit_fit_overrides_default(): void
    {
        $params = $this->processor->buildParams(['w' => '300', 'h' => '200', 'fit' => 'crop']);

        $this->assertSame('crop', $params['fit']);
    }

    public function test_build_params_ignores_fit_without_dimensions(): void
    {
        // fit без w/h не передається до Glide
        $params = $this->processor->buildParams(['fit' => 'crop']);

        $this->assertArrayNotHasKey('fit', $params);
    }

    // -------------------------------------------------------------------------
    // outputExtension()
    // -------------------------------------------------------------------------

    public function test_output_extension_from_validated_fm(): void
    {
        $ext = $this->processor->outputExtension(['fm' => 'png'], []);
        $this->assertSame('png', $ext);
    }

    public function test_output_extension_jpg_from_validated(): void
    {
        $ext = $this->processor->outputExtension(['fm' => 'jpg'], []);
        $this->assertSame('jpg', $ext);
    }

    public function test_output_extension_pjpg_normalizes_to_jpg(): void
    {
        $ext = $this->processor->outputExtension([], ['fm' => 'pjpg']);
        $this->assertSame('jpg', $ext);
    }

    public function test_output_extension_jpeg_normalizes_to_jpg(): void
    {
        $ext = $this->processor->outputExtension(['fm' => 'jpeg'], []);
        $this->assertSame('jpg', $ext);
    }

    public function test_output_extension_webp_default(): void
    {
        $ext = $this->processor->outputExtension([], []);
        $this->assertSame('webp', $ext);
    }

    public function test_output_extension_from_glide_params_when_no_validated_fm(): void
    {
        $ext = $this->processor->outputExtension([], ['fm' => 'png']);
        $this->assertSame('png', $ext);
    }

    public function test_output_extension_validated_fm_takes_precedence_over_glide_params(): void
    {
        $ext = $this->processor->outputExtension(['fm' => 'webp'], ['fm' => 'png']);
        $this->assertSame('webp', $ext);
    }
}

