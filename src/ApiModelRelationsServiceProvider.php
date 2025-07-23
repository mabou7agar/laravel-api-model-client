<?php

namespace ApiModelRelations;

use ApiModelRelations\Console\Commands\GenerateApiModelDocumentation;
use ApiModelRelations\Console\Commands\GenerateApiModelFromSwagger;
use ApiModelRelations\Contracts\ApiClientInterface;
use ApiModelRelations\Macros\ApiModelMacros;
use ApiModelRelations\Services\ApiClient;
use ApiModelRelations\Services\ApiPipeline;
use ApiModelRelations\Services\Auth\ApiKeyAuth;
use ApiModelRelations\Services\Auth\BasicAuth;
use ApiModelRelations\Services\Auth\BearerTokenAuth;
use ApiModelRelations\Middleware\LoggingMiddleware;
use ApiModelRelations\Middleware\RateLimitMiddleware;
use Illuminate\Support\ServiceProvider;

class ApiModelRelationsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/Config/api-model-relations.php' => config_path('api-model-relations.php'),
        ], 'config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateApiModelFromSwagger::class,
                GenerateApiModelDocumentation::class,
            ]);
        }
        
        // Register macros
        ApiModelMacros::register();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/Config/api-model-relations.php', 'api-model-relations'
        );

        // Register API client
        $this->app->singleton('api-client', function ($app) {
            $config = $app['config']['api-model-relations'];
            $client = new ApiClient($config['client']['base_url'] ?? null);
            
            // Set up authentication if configured
            if (isset($config['auth']['strategy'])) {
                $authStrategy = $this->resolveAuthStrategy($config['auth']);
                if ($authStrategy) {
                    $client->setAuthStrategy($authStrategy);
                }
            }
            
            return $client;
        });
        
        // Bind ApiClientInterface to the singleton
        $this->app->bind(ApiClientInterface::class, function ($app) {
            return $app->make('api-client');
        });
        
        // Register API pipeline
        $this->app->singleton('api-pipeline', function ($app) {
            $pipeline = new ApiPipeline();
            $config = $app['config']['api-model-relations'];
            
            // Add logging middleware if enabled
            if ($config['error_handling']['log_requests'] ?? true) {
                $pipeline->pipe(new LoggingMiddleware());
            }
            
            // Add rate limiting middleware if enabled
            if ($config['rate_limiting']['enabled'] ?? true) {
                $pipeline->pipe(new RateLimitMiddleware(
                    $config['rate_limiting']['max_attempts'] ?? 60,
                    $config['rate_limiting']['decay_minutes'] ?? 1
                ));
            }
            
            return $pipeline;
        });
    }
    
    /**
     * Resolve the authentication strategy from configuration.
     *
     * @param array $authConfig
     * @return \ApiModelRelations\Contracts\AuthStrategyInterface|null
     */
    protected function resolveAuthStrategy(array $authConfig)
    {
        $strategy = $authConfig['strategy'] ?? null;
        $credentials = $authConfig['credentials'] ?? [];
        
        switch ($strategy) {
            case 'bearer':
                return new BearerTokenAuth($credentials['token'] ?? null);
                
            case 'basic':
                return new BasicAuth(
                    $credentials['username'] ?? null,
                    $credentials['password'] ?? null
                );
                
            case 'api_key':
                return new ApiKeyAuth(
                    $credentials['api_key'] ?? null,
                    $credentials['header_name'] ?? 'X-API-KEY',
                    $credentials['use_query_param'] ?? false,
                    $credentials['query_param_name'] ?? 'api_key'
                );
                
            default:
                return null;
        }
    }
}
