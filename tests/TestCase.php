<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets\Tests;

use Fomvasss\Imagepresets\ImagepresetServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ImagepresetServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('imagepresets.disk', 'public');
        $app['config']->set('imagepresets.path', 'imagepresets');
        $app['config']->set('imagepresets.driver', 'gd');
        $app['config']->set('imagepresets.quality', 80);
        $app['config']->set('imagepresets.format', 'webp');
        $app['config']->set('imagepresets.cache_max_age', 31536000);
        $app['config']->set('imagepresets.default_fit_both', 'fill');
        $app['config']->set('imagepresets.default_fit_one', 'max');
        $app['config']->set('imagepresets.allowed_sizes', [[300, 200], [600, 400]]);
        $app['config']->set('imagepresets.allowed_widths', [100, 300, 600, 1200]);
        $app['config']->set('imagepresets.allowed_heights', [100, 300, 600]);
        $app['config']->set('imagepresets.allowed_qualities', [80, 90]);
        $app['config']->set('imagepresets.allowed_fits', ['max', 'fill', 'crop']);
        $app['config']->set('imagepresets.allowed_formats', ['webp', 'jpg', 'png', 'gif']);
        $app['config']->set('imagepresets.allowed_hosts', []);
        $app['config']->set('imagepresets.max_download_bytes', 20 * 1024 * 1024);
        $app['config']->set('imagepresets.max_image_pixels', 150_000_000);
        $app['config']->set('imagepresets.svg.sanitize', true);
        $app['config']->set('imagepresets.svg.remove_remote_references', true);
        $app['config']->set('imagepresets.svg.rasterize', false);
        $app['config']->set('imagepresets.source_dir', sys_get_temp_dir().'/imagepreset_sources_test');
        $app['config']->set('imagepresets.temp_dir', sys_get_temp_dir().'/imagepreset_temp_test');
        $app['config']->set('app.url', 'http://localhost');
    }
}

