<?php

namespace Contextify\LaravelResourceOptimizer;

use Illuminate\Support\ServiceProvider;

/**
 * Class ResourceOptimizerServiceProvider
 *
 * Laravel service provider for the Resource Optimizer package.
 * Handles package configuration management, registration of services,
 * middleware, and publishing of configuration files for customization.
 *
 * This provider automatically registers optimization features including
 * N+1 query detection, performance monitoring, and intelligent caching.
 *
 * @package Contextify\LaravelResourceOptimizer
 */
class ResourceOptimizerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services and publish configuration.
     *
     * This method is called after all service providers have been registered.
     * It handles the publishing of configuration files when the application
     * is running in console mode (e.g., during artisan commands).
     *
     * Users can publish the configuration file using:
     * php artisan vendor:publish --provider="Contextify\LaravelResourceOptimizer\ResourceOptimizerServiceProvider" --tag="config"
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/resource-optimizer.php' => config_path('resource-optimizer.php'),
            ], 'config');
        }
    }

    /**
     * Register package services and configuration.
     *
     * This method is called during the service provider registration phase.
     * It merges the package's default configuration with any user-published
     * configuration, ensuring that default values are available even if
     * the user hasn't published the config file.
     *
     * The configuration will be accessible via config('resource-context.*')
     * throughout the application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/resource-optimizer.php',
            'resource-optimizer'
        );

        // Register optimization services
        $this->app->singleton('resource-optimizer.monitor', function ($app) {
            return new Services\PerformanceMonitor();
        });

        $this->app->singleton('resource-optimizer.detector', function ($app) {
            return new Services\QueryDetector();
        });

        $this->app->singleton('resource-optimizer.cache', function ($app) {
            return new Services\ResourceCache();
        });
    }
}