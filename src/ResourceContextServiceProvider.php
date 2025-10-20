<?php

namespace Contextify\LaravelResourceContext;

use Illuminate\Support\ServiceProvider;

/**
 * Class ResourceContextServiceProvider
 *
 * Laravel service provider for the Resource Context package.
 * Handles package configuration management, registration of services,
 * and publishing of configuration files for customization.
 *
 * This provider automatically registers the package configuration
 * and allows users to publish and customize the configuration file
 * when running in console mode.
 *
 * @package Contextify\LaravelResourceContext
 */
class ResourceContextServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services and publish configuration.
     *
     * This method is called after all service providers have been registered.
     * It handles the publishing of configuration files when the application
     * is running in console mode (e.g., during artisan commands).
     *
     * Users can publish the configuration file using:
     * php artisan vendor:publish --provider="Contextify\LaravelResourceContext\ResourceContextServiceProvider" --tag="config"
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/resource-context.php' => config_path('resource-context.php'),
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
            __DIR__.'/../config/resource-context.php',
            'resource-context'
        );
    }
}