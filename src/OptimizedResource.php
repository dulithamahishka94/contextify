<?php

namespace Contextify\LaravelResourceOptimizer;

use Illuminate\Http\Resources\Json\JsonResource;
use Contextify\LaravelResourceOptimizer\Traits\QueryDetection;
use Contextify\LaravelResourceOptimizer\Traits\PerformanceMonitoring;
use Contextify\LaravelResourceOptimizer\Traits\SmartCaching;
use Contextify\LaravelResourceOptimizer\Traits\CompositionHelpers;

/**
 * Class OptimizedResource
 *
 * An enhanced JSON Resource class that provides comprehensive optimization features
 * for Laravel API resources, including N+1 query detection, performance monitoring,
 * smart caching, and development tools to improve API performance and developer experience.
 *
 * Key features:
 * - Automatic N+1 query detection and warnings
 * - Resource transformation performance monitoring
 * - Intelligent caching layer for expensive operations
 * - Smart eager loading suggestions
 * - Enhanced composition and validation helpers
 * - Development debugging tools
 *
 * @package Contextify\LaravelResourceOptimizer
 */
class OptimizedResource extends JsonResource
{
    use QueryDetection, PerformanceMonitoring, SmartCaching, CompositionHelpers {
        QueryDetection::addToDebugReport as addQueryDebugReport;
        PerformanceMonitoring::addToDebugReport insteadof QueryDetection;
    }

    /**
     * Unique identifier for this resource instance used in performance tracking.
     *
     * @var string
     */
    protected string $resourceId;

    /**
     * Whether to enable performance monitoring for this resource.
     *
     * @var bool
     */
    protected bool $monitorPerformance = true;

    /**
     * Whether to enable N+1 query detection for this resource.
     *
     * @var bool
     */
    protected bool $detectQueries = true;

    /**
     * Whether to enable caching for this resource.
     *
     * @var bool
     */
    protected bool $enableCaching = true;

    /**
     * Cache TTL in seconds for this resource.
     *
     * @var int
     */
    protected int $cacheTtl = 3600;

    /**
     * Initialize the optimized resource with performance tracking.
     *
     * @param mixed $resource The underlying resource data
     */
    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->resourceId = $this->generateResourceId();
        $this->initializeOptimizations();
    }

    /**
     * Transform the resource into an array with full optimization features.
     *
     * This method wraps the transformation process with performance monitoring,
     * query detection, and caching capabilities while maintaining compatibility
     * with Laravel's resource system.
     *
     * @param \Illuminate\Http\Request|null $request The HTTP request instance
     * @return array<string, mixed> The optimized transformed resource
     */
    public function toArray($request = null)
    {
        return $this->withOptimizations(function () use ($request) {
            return $this->transformResource($request);
        });
    }

    /**
     * Transform the resource data (override this method in your resource classes).
     *
     * This method should be overridden in your resource classes instead of toArray().
     * It provides the actual transformation logic while toArray() handles the
     * optimization infrastructure.
     *
     * @param \Illuminate\Http\Request|null $request The HTTP request instance
     * @return mixed The transformed resource data
     */
    protected function transformResource($request)
    {
        return parent::toArray($request);
    }

    /**
     * Execute transformation with all optimization features enabled.
     *
     * @param callable $transformation The transformation function to execute
     * @return mixed The result of the transformation
     */
    protected function withOptimizations(callable $transformation)
    {
        $cacheKey = $this->getCacheKey();

        // Try cache first if enabled
        if ($this->enableCaching && $cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }

        // Start monitoring
        $this->startPerformanceMonitoring();
        $this->startQueryDetection();

        try {
            $result = $transformation();

            // Cache the result if enabled
            if ($this->enableCaching) {
                $this->storeInCache($cacheKey, $result, $this->cacheTtl);
            }

            return $result;
        } finally {
            $this->stopQueryDetection();
            $this->stopPerformanceMonitoring();
        }
    }

    /**
     * Generate a unique identifier for this resource instance.
     *
     * @return string Unique resource identifier
     */
    protected function generateResourceId(): string
    {
        return uniqid('resource_', true);
    }

    /**
     * Initialize optimization features based on configuration.
     *
     * @return void
     */
    protected function initializeOptimizations(): void
    {
        $config = config('resource-optimizer', []);

        $this->monitorPerformance = $config['performance_monitoring'] ?? true;
        $this->detectQueries = $config['query_detection']['enabled'] ?? true;
        $this->enableCaching = $config['caching']['enabled'] ?? true;
        $this->cacheTtl = $config['caching']['default_ttl'] ?? 3600;
    }

    /**
     * Disable performance monitoring for this resource instance.
     *
     * @return $this
     */
    public function withoutMonitoring(): self
    {
        $this->monitorPerformance = false;
        return $this;
    }

    /**
     * Disable query detection for this resource instance.
     *
     * @return $this
     */
    public function withoutQueryDetection(): self
    {
        $this->detectQueries = false;
        return $this;
    }

    /**
     * Disable caching for this resource instance.
     *
     * @return $this
     */
    public function withoutCaching(): self
    {
        $this->enableCaching = false;
        return $this;
    }

    /**
     * Set custom cache TTL for this resource instance.
     *
     * @param int $ttl Cache TTL in seconds
     * @return $this
     */
    public function withCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Get the resource ID for tracking purposes.
     *
     * @return string The resource ID
     */
    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    /**
     * Check if an Eloquent relationship is loaded.
     *
     * @param string $relationship The relationship name to check
     * @return bool True if the relationship is loaded
     */
    protected function relationLoaded(string $relationship): bool
    {
        if (!is_object($this->resource)) {
            return false;
        }

        if (method_exists($this->resource, 'relationLoaded')) {
            return $this->resource->relationLoaded($relationship);
        }

        return property_exists($this->resource, $relationship);
    }

    /**
     * Get loaded relationships for eager loading analysis.
     *
     * @return array<string> Array of loaded relationship names
     */
    protected function getLoadedRelationships(): array
    {
        if (!is_object($this->resource) || !method_exists($this->resource, 'getRelations')) {
            return [];
        }

        return array_keys($this->resource->getRelations());
    }
}