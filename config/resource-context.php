<?php

/**
 * Laravel Resource Context Configuration
 *
 * Configuration settings for the Laravel Resource Context package.
 * These settings control the behavior of context propagation and
 * debugging features throughout your application.
 *
 * You can publish this configuration file using:
 * php artisan vendor:publish --provider="Contextify\LaravelResourceContext\ResourceContextServiceProvider" --tag="config"
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Context Propagation
    |--------------------------------------------------------------------------
    |
    | When enabled, context will be automatically propagated from parent
    | resources to child resources. This is the core feature that allows
    | nested resources to access parent data without additional queries.
    |
    */
    'auto_propagate' => true,

    /*
    |--------------------------------------------------------------------------
    | Context Stack Limit
    |--------------------------------------------------------------------------
    |
    | Maximum depth of the context stack to prevent infinite recursion
    | in deeply nested resource structures. Adjust this value based on
    | your application's maximum nesting requirements.
    |
    */
    'context_limit' => 100,

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable debug mode for development to get additional logging and
    | error information about context propagation. Should be disabled
    | in production for optimal performance.
    |
    */
    'debug' => env('RESOURCE_CONTEXT_DEBUG', false),
];