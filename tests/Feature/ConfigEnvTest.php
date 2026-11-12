<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests\Feature;

use Fomvasss\Imagepresets\Tests\TestCase;

final class ConfigEnvTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('IMAGEPRESET_QUALITY');
        putenv('IMAGEPRESET_FORMAT');

        parent::tearDown();
    }

    public function test_quality_and_format_are_read_from_env(): void
    {
        putenv('IMAGEPRESET_QUALITY=90');
        putenv('IMAGEPRESET_FORMAT=avif');

        $config = require __DIR__.'/../../config/imagepresets.php';

        $this->assertSame(90, $config['quality']);
        $this->assertSame('avif', $config['format']);
    }

    public function test_quality_and_format_defaults(): void
    {
        $config = require __DIR__.'/../../config/imagepresets.php';

        $this->assertSame(80, $config['quality']);
        $this->assertSame('webp', $config['format']);
    }
}
