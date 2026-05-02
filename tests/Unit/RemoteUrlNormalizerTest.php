<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Unit;

use Fomvasss\Imagepresets\Support\RemoteUrlNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoteUrlNormalizer::class)]
final class RemoteUrlNormalizerTest extends TestCase
{
    private RemoteUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new RemoteUrlNormalizer();
    }

    // -------------------------------------------------------------------------
    // isRemote()
    // -------------------------------------------------------------------------

    public function test_is_remote_returns_true_for_http(): void
    {
        $this->assertTrue($this->normalizer->isRemote('http://example.com/image.jpg'));
    }

    public function test_is_remote_returns_true_for_https(): void
    {
        $this->assertTrue($this->normalizer->isRemote('https://example.com/image.jpg'));
    }

    public function test_is_remote_returns_false_for_local_path(): void
    {
        $this->assertFalse($this->normalizer->isRemote('images/photo.jpg'));
    }

    public function test_is_remote_returns_false_for_ftp(): void
    {
        $this->assertFalse($this->normalizer->isRemote('ftp://example.com/image.jpg'));
    }

    // -------------------------------------------------------------------------
    // normalize()
    // -------------------------------------------------------------------------

    public function test_normalize_lowercases_scheme(): void
    {
        $result = $this->normalizer->normalize('HTTPS://Example.COM/image.jpg');
        $this->assertIsString($result);
        $this->assertStringStartsWith('https://', $result);
    }

    public function test_normalize_encodes_path_segments(): void
    {
        $result = $this->normalizer->normalize('https://example.com/path/my image.jpg');
        $this->assertIsString($result);
        $this->assertStringContainsString('my%20image.jpg', $result);
    }

    public function test_normalize_does_not_double_encode(): void
    {
        $result = $this->normalizer->normalize('https://example.com/path/my%20image.jpg');
        $this->assertIsString($result);
        $this->assertStringContainsString('my%20image.jpg', $result);
        $this->assertStringNotContainsString('my%2520image', $result);
    }

    public function test_normalize_encodes_query_params(): void
    {
        $result = $this->normalizer->normalize('https://example.com/img?name=hello world&size=big');
        $this->assertIsString($result);
        $this->assertStringContainsString('name=hello%20world', $result);
    }

    public function test_normalize_returns_url_as_is_for_non_remote_scheme(): void
    {
        // ftp:// не є remote (лише http/https) — normalize повертає рядок як є
        $result = $this->normalizer->normalize('ftp://example.com/image.jpg');
        $this->assertSame('ftp://example.com/image.jpg', $result);
    }

    public function test_normalize_returns_false_for_missing_host(): void
    {
        $result = $this->normalizer->normalize('https:///image.jpg');
        $this->assertFalse($result);
    }

    public function test_normalize_preserves_port(): void
    {
        $result = $this->normalizer->normalize('https://example.com:8080/image.jpg');
        $this->assertIsString($result);
        $this->assertStringContainsString(':8080', $result);
    }

    public function test_normalize_local_path_returns_as_is(): void
    {
        $result = $this->normalizer->normalize('images/photo.jpg');
        $this->assertSame('images/photo.jpg', $result);
    }

    // -------------------------------------------------------------------------
    // extractHost()
    // -------------------------------------------------------------------------

    public function test_extract_host_from_url(): void
    {
        $this->assertSame('example.com', $this->normalizer->extractHost('https://example.com/image.jpg'));
    }

    public function test_extract_host_returns_empty_for_invalid(): void
    {
        $this->assertSame('', $this->normalizer->extractHost('not-a-url'));
    }
}

