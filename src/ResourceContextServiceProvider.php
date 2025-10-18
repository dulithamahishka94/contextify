<?php

namespace Contextify\LaravelResourceContext;

use Illuminate\Support\ServiceProvider;

class ResourceContextServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/resource-context.php' => config_path('resource-context.php'),
            ], 'config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/resource-context.php',
            'resource-context'
        );
    }
}