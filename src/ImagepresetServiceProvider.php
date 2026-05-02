<?php

declare(strict_types=1);

namespace Fomvasss\Imagepresets;

use Fomvasss\Imagepresets\Console\ClearCommand;
use Fomvasss\Imagepresets\Services\ImagepresetService;
use Fomvasss\Imagepresets\Support\GlideProcessor;
use Fomvasss\Imagepresets\Support\RemoteUrlNormalizer;
use Fomvasss\Imagepresets\Support\ResponseBuilder;
use Fomvasss\Imagepresets\Support\SourceResolver;
use Fomvasss\Imagepresets\Support\SvgProcessor;
use Fomvasss\Imagepresets\Validation\ImagepresetValidator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class ImagepresetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/imagepresets.php', 'imagepresets');

        $this->app->singleton(RemoteUrlNormalizer::class);
        $this->app->singleton(SourceResolver::class);
        $this->app->singleton(GlideProcessor::class);
        $this->app->singleton(SvgProcessor::class);
        $this->app->singleton(ResponseBuilder::class);
        $this->app->singleton(ImagepresetValidator::class);
        $this->app->singleton(ImagepresetService::class);

        $this->app->alias(ImagepresetService::class, 'imagepresets');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/imagepresets.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/imagepresets.php' => config_path('imagepresets.php'),
            ], 'imagepresets-config');

            $this->commands([ClearCommand::class]);
        }

        // @imagepreset('storage/images/photo.jpg', ['w' => 400, 'fm' => 'webp'])
        // Generates a URL via the imagepreset_url() helper.
        Blade::directive('imagepreset', static function (string $expression): string {
            return "<?php echo imagepreset_url({$expression}); ?>";
        });
    }
}
