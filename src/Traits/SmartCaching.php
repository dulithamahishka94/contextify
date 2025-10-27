<?php

namespace Contextify\LaravelResourceOptimizer\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait SmartCaching
 *
 * Provides intelligent caching capabilities for Laravel resource transformations.
 * Automatically caches expensive transformations and provides cache invalidation
 * strategies to ensure data consistency while improving performance.
 *
 * @package Contextify\LaravelResourceOptimizer\Traits
 */
trait SmartCaching
{
    /**
     * Cache store to use for resource caching.
     *
     * @var string|null
     */
    protected ?string $cacheStore = null;

    /**
     * Cache tags for this resource (if supported by cache driver).
     *
     * @var array<string>
     */
    protected array $cacheTags = [];

    /**
     * Get cache key for this resource transformation.
     *
     * @return string Cache key
     */
    protected function getCacheKey(): string
    {
        $baseKey = sprintf(
            'resource_optimizer:%s:%s',
            class_basename(static::class),
            $this->generateCacheIdentifier()
        );

        // Include request parameters that might affect output
        if ($request = request()) {
            $relevantParams = $this->getRelevantRequestParameters($request);
            if (!empty($relevantParams)) {
                $baseKey .= ':' . md5(serialize($relevantParams));
            }
        }

        return $baseKey;
    }

    /**
     * Generate a unique identifier for cache key based on resource data.
     *
     * @return string Cache identifier
     */
    protected function generateCacheIdentifier(): string
    {
        if (is_null($this->resource)) {
            return 'null';
        }

        if (is_scalar($this->resource)) {
            return (string) $this->resource;
        }

        if (is_array($this->resource)) {
            return md5(serialize($this->resource));
        }

        if (is_object($this->resource)) {
            // For Eloquent models, use ID and updated_at if available
            if (method_exists($this->resource, 'getKey') && method_exists($this->resource, 'updated_at')) {
                $key = $this->resource->getKey();
                $updatedAt = $this->resource->updated_at?->timestamp ?? time();
                return "{$key}:{$updatedAt}";
            }

            // For other objects, use class and object hash
            return get_class($this->resource) . ':' . spl_object_hash($this->resource);
        }

        return md5(serialize($this->resource));
    }

    /**
     * Get relevant request parameters that might affect resource output.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed> Relevant request parameters
     */
    protected function getRelevantRequestParameters($request): array
    {
        $relevant = [];

        // Include common parameters that affect resource output
        $commonParams = ['include', 'fields', 'format', 'locale', 'timezone'];

        foreach ($commonParams as $param) {
            if ($request->has($param)) {
                $relevant[$param] = $request->get($param);
            }
        }

        // Allow resources to define custom relevant parameters
        if (method_exists($this, 'getCacheRelevantParameters')) {
            $customParams = $this->getCacheRelevantParameters($request);
            $relevant = array_merge($relevant, $customParams);
        }

        return $relevant;
    }

    /**
     * Get cached resource transformation if available.
     *
     * @param string $cacheKey Cache key to retrieve
     * @return mixed|null Cached data or null if not found
     */
    protected function getFromCache(string $cacheKey)
    {
        try {
            $cache = $this->getCacheInstance();

            if (!empty($this->cacheTags) && method_exists($cache, 'tags')) {
                return $cache->tags($this->cacheTags)->get($cacheKey);
            }

            return $cache->get($cacheKey);
        } catch (\Exception $e) {
            Log::warning("Failed to retrieve resource from cache", [
                'cache_key' => $cacheKey,
                'resource_class' => static::class,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Store resource transformation in cache.
     *
     * @param string $cacheKey Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Cache TTL in seconds
     * @return bool Success status
     */
    protected function storeInCache(string $cacheKey, $data, int $ttl): bool
    {
        try {
            $cache = $this->getCacheInstance();

            if (!empty($this->cacheTags) && method_exists($cache, 'tags')) {
                return $cache->tags($this->cacheTags)->put($cacheKey, $data, $ttl);
            }

            return $cache->put($cacheKey, $data, $ttl);
        } catch (\Exception $e) {
            Log::warning("Failed to store resource in cache", [
                'cache_key' => $cacheKey,
                'resource_class' => static::class,
                'ttl' => $ttl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get cache instance to use for this resource.
     *
     * @return \Illuminate\Contracts\Cache\Repository Cache instance
     */
    protected function getCacheInstance()
    {
        if ($this->cacheStore) {
            return Cache::store($this->cacheStore);
        }

        return Cache::store(config('resource-optimizer.caching.store', config('cache.default')));
    }

    /**
     * Set cache store for this resource instance.
     *
     * @param string $store Cache store name
     * @return $this
     */
    public function withCacheStore(string $store): self
    {
        $this->cacheStore = $store;
        return $this;
    }

    /**
     * Set cache tags for this resource instance.
     *
     * @param array<string> $tags Cache tags
     * @return $this
     */
    public function withCacheTags(array $tags): self
    {
        $this->cacheTags = $tags;
        return $this;
    }

    /**
     * Add cache tag for this resource instance.
     *
     * @param string $tag Cache tag to add
     * @return $this
     */
    public function addCacheTag(string $tag): self
    {
        if (!in_array($tag, $this->cacheTags)) {
            $this->cacheTags[] = $tag;
        }

        return $this;
    }

    /**
     * Invalidate cache for this resource.
     *
     * @return bool Success status
     */
    public function invalidateCache(): bool
    {
        try {
            $cache = $this->getCacheInstance();
            $cacheKey = $this->getCacheKey();

            if (!empty($this->cacheTags) && method_exists($cache, 'tags')) {
                return $cache->tags($this->cacheTags)->forget($cacheKey);
            }

            return $cache->forget($cacheKey);
        } catch (\Exception $e) {
            Log::warning("Failed to invalidate resource cache", [
                'resource_class' => static::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invalidate cache by tags (if supported by cache driver).
     *
     * @param array<string> $tags Cache tags to invalidate
     * @return bool Success status
     */
    public static function invalidateCacheByTags(array $tags): bool
    {
        try {
            $cache = Cache::store(config('resource-optimizer.caching.store', config('cache.default')));

            if (method_exists($cache, 'tags')) {
                return $cache->tags($tags)->flush();
            }

            Log::info("Cache driver does not support tags, skipping tag-based invalidation");
            return true;
        } catch (\Exception $e) {
            Log::warning("Failed to invalidate cache by tags", [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Warm up cache for this resource.
     *
     * @param \Illuminate\Http\Request|null $request Request instance
     * @return bool Success status
     */
    public function warmUpCache($request = null): bool
    {
        try {
            // Temporarily disable caching to force regeneration
            $originalCaching = $this->enableCaching;
            $this->enableCaching = false;

            // Generate fresh data
            $data = $this->transformResource($request);

            // Re-enable caching and store the data
            $this->enableCaching = $originalCaching;
            $cacheKey = $this->getCacheKey();

            return $this->storeInCache($cacheKey, $data, $this->cacheTtl);
        } catch (\Exception $e) {
            Log::warning("Failed to warm up cache for resource", [
                'resource_class' => static::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get cache statistics for this resource class.
     *
     * @return array<string, mixed> Cache statistics
     */
    public static function getCacheStats(): array
    {
        $stats = [
            'resource_class' => static::class,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'cache_stores' => 0,
            'cache_failures' => 0,
        ];

        // This would typically be implemented with a cache driver that supports statistics
        // For now, return basic structure
        return $stats;
    }

    /**
     * Check if caching is enabled and properly configured.
     *
     * @return bool True if caching is available
     */
    protected function isCachingAvailable(): bool
    {
        if (!$this->enableCaching) {
            return false;
        }

        try {
            $cache = $this->getCacheInstance();
            return $cache !== null;
        } catch (\Exception $e) {
            Log::warning("Cache is not available", [
                'resource_class' => static::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate automatic cache tags based on resource data.
     *
     * @return array<string> Generated cache tags
     */
    protected function generateAutoCacheTags(): array
    {
        $tags = [
            'resource_optimizer',
            'resource:' . class_basename(static::class),
        ];

        // Add model-specific tags for Eloquent models
        if (is_object($this->resource) && method_exists($this->resource, 'getTable')) {
            $tags[] = 'model:' . $this->resource->getTable();

            if (method_exists($this->resource, 'getKey')) {
                $tags[] = 'model:' . $this->resource->getTable() . ':' . $this->resource->getKey();
            }
        }

        return $tags;
    }
}