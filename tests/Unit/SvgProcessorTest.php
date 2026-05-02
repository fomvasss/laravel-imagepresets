<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Unit;

use Fomvasss\Imagepresets\Support\SvgProcessor;
use Fomvasss\Imagepresets\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SvgProcessor::class)]
final class SvgProcessorTest extends TestCase
{
    private SvgProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new SvgProcessor();
    }

    public function test_process_stores_svg_to_disk(): void
    {
        Storage::fake('public');

        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>';
        $sourcePath = sys_get_temp_dir().'/test_svg_'.uniqid().'.svg';
        file_put_contents($sourcePath, $svgContent);

        $disk = Storage::disk('public');
        $result = $this->processor->process($disk, 'imagepresets', 'test.svg', $sourcePath);

        $this->assertNotNull($result);
        Storage::disk('public')->assertExists('imagepresets/test.svg');

        unlink($sourcePath);
    }

    public function test_process_returns_existing_path_if_already_cached(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('imagepresets/cached.svg', '<svg/>');

        $disk   = Storage::disk('public');
        $result = $this->processor->process($disk, 'imagepresets', 'cached.svg', '/nonexistent.svg');

        $this->assertNotNull($result);
    }

    public function test_sanitize_removes_script_tags(): void
    {
        Storage::fake('public');

        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("xss")</script><rect/></svg>';
        $sourcePath   = sys_get_temp_dir().'/test_xss_'.uniqid().'.svg';
        file_put_contents($sourcePath, $maliciousSvg);

        config(['imagepresets.svg.sanitize' => true]);

        $disk = Storage::disk('public');
        $this->processor->process($disk, 'imagepresets', 'xss.svg', $sourcePath);

        $stored = Storage::disk('public')->get('imagepresets/xss.svg');
        $this->assertStringNotContainsString('<script>', (string) $stored);
        $this->assertStringNotContainsString('alert', (string) $stored);

        unlink($sourcePath);
    }

    public function test_sanitize_removes_event_attributes(): void
    {
        Storage::fake('public');

        $svg        = '<svg xmlns="http://www.w3.org/2000/svg"><rect onload="evil()" width="10"/></svg>';
        $sourcePath = sys_get_temp_dir().'/test_event_'.uniqid().'.svg';
        file_put_contents($sourcePath, $svg);

        config(['imagepresets.svg.sanitize' => true]);

        $disk = Storage::disk('public');
        $this->processor->process($disk, 'imagepresets', 'event.svg', $sourcePath);

        $stored = Storage::disk('public')->get('imagepresets/event.svg');
        $this->assertStringNotContainsString('onload', (string) $stored);

        unlink($sourcePath);
    }

    public function test_sanitize_removes_javascript_uri(): void
    {
        Storage::fake('public');

        $svg        = '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:void(0)"><rect/></a></svg>';
        $sourcePath = sys_get_temp_dir().'/test_js_'.uniqid().'.svg';
        file_put_contents($sourcePath, $svg);

        config(['imagepresets.svg.sanitize' => true]);

        $disk = Storage::disk('public');
        $this->processor->process($disk, 'imagepresets', 'js.svg', $sourcePath);

        $stored = Storage::disk('public')->get('imagepresets/js.svg');
        $this->assertStringNotContainsString('javascript:', (string) $stored);

        unlink($sourcePath);
    }

    public function test_no_sanitize_preserves_original_content(): void
    {
        Storage::fake('public');

        $svg        = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10"/></svg>';
        $sourcePath = sys_get_temp_dir().'/test_nosani_'.uniqid().'.svg';
        file_put_contents($sourcePath, $svg);

        config(['imagepresets.svg.sanitize' => false]);

        $disk = Storage::disk('public');
        $this->processor->process($disk, 'imagepresets', 'raw.svg', $sourcePath);

        $stored = Storage::disk('public')->get('imagepresets/raw.svg');
        $this->assertSame($svg, (string) $stored);

        unlink($sourcePath);
    }
}

