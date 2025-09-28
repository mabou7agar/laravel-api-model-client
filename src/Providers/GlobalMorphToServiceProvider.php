<?php

namespace MTechStack\LaravelApiModelClient\Providers;

use Illuminate\Support\ServiceProvider;
use MTechStack\LaravelApiModelClient\Traits\GlobalMorphToOverride;

/**
 * Service Provider for Global MorphTo Override functionality.
 * 
 * This provider automatically registers the global morphTo override system
 * that detects when morphTo relationships target ApiModel classes and
 * uses MorphToFromApi instead of the standard MorphTo relation.
 */
class GlobalMorphToServiceProvider extends ServiceProvider
{
    use GlobalMorphToOverride;

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/api-model-client.php',
            'api-model-client'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../../config/api-model-client.php' => config_path('api-model-client.php'),
        ], 'api-model-client-config');

        // Register global morphTo override if enabled
        if (config('api-model-client.enable_global_morph_override', true)) {
            $this->registerGlobalMorphToOverride();
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }
}
