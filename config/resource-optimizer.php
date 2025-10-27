<?php

/**
 * Laravel Resource Optimizer Configuration
 *
 * Configuration settings for the Laravel Resource Optimizer package.
 * These settings control N+1 query detection, performance monitoring,
 * caching, and other optimization features throughout your application.
 *
 * You can publish this configuration file using:
 * php artisan vendor:publish --provider="Contextify\LaravelResourceOptimizer\ResourceOptimizerServiceProvider" --tag="config"
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Enable performance monitoring for resource transformations. This tracks
    | execution time, memory usage, and provides analytics to identify
    | bottlenecks and optimization opportunities.
    |
    */
    'performance_monitoring' => env('RESOURCE_OPTIMIZER_PERFORMANCE', true),

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure thresholds for performance warnings. Resources exceeding
    | these limits will trigger warnings in the logs.
    |
    */
    'performance' => [
        'slow_threshold' => env('RESOURCE_OPTIMIZER_SLOW_THRESHOLD', 0.1), // seconds
        'memory_threshold' => env('RESOURCE_OPTIMIZER_MEMORY_THRESHOLD', 10 * 1024 * 1024), // bytes
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Detection
    |--------------------------------------------------------------------------
    |
    | Configure N+1 query detection behavior and thresholds for monitoring
    | database queries during resource transformations.
    |
    */
    'query_detection' => [
        'enabled' => env('RESOURCE_OPTIMIZER_QUERY_DETECTION', true),
        'threshold' => env('RESOURCE_OPTIMIZER_QUERY_THRESHOLD', 5), // number of queries
        'slow_threshold' => env('RESOURCE_OPTIMIZER_SLOW_QUERY_THRESHOLD', 100), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Caching
    |--------------------------------------------------------------------------
    |
    | Configure intelligent caching for resource transformations to improve
    | performance for expensive operations.
    |
    */
    'caching' => [
        'enabled' => env('RESOURCE_OPTIMIZER_CACHING', true),
        'store' => env('RESOURCE_OPTIMIZER_CACHE_STORE', null), // uses default cache store if null
        'default_ttl' => env('RESOURCE_OPTIMIZER_CACHE_TTL', 3600), // seconds
        'auto_tags' => env('RESOURCE_OPTIMIZER_AUTO_TAGS', true), // automatic cache tag generation
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable debug mode for development to get detailed logging, performance
    | metrics, and optimization suggestions. Should be disabled in production.
    |
    */
    'debug_mode' => env('RESOURCE_OPTIMIZER_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Eager Loading Analysis
    |--------------------------------------------------------------------------
    |
    | Enable analysis of relationship loading patterns to suggest optimal
    | eager loading strategies.
    |
    */
    'eager_loading_analysis' => env('RESOURCE_OPTIMIZER_EAGER_LOADING', true),

    /*
    |--------------------------------------------------------------------------
    | Resource Validation
    |--------------------------------------------------------------------------
    |
    | Enable validation of resource data consistency to catch potential
    | data integrity issues.
    |
    */
    'validation' => [
        'enabled' => env('RESOURCE_OPTIMIZER_VALIDATION', false),
        'strict_mode' => env('RESOURCE_OPTIMIZER_VALIDATION_STRICT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how optimization information is logged.
    |
    */
    'logging' => [
        'channel' => env('RESOURCE_OPTIMIZER_LOG_CHANNEL', null), // uses default log channel if null
        'level' => env('RESOURCE_OPTIMIZER_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Integration
    |--------------------------------------------------------------------------
    |
    | Configure middleware for automatic optimization features.
    |
    */
    'middleware' => [
        'auto_enable' => env('RESOURCE_OPTIMIZER_AUTO_ENABLE', true),
        'routes' => env('RESOURCE_OPTIMIZER_ROUTES', 'api/*'), // route patterns to monitor
    ],
];