<?php

namespace Contextify\LaravelResourceOptimizer\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Class ResourceCache
 *
 * Centralized caching service for resource transformations with
 * intelligent cache management and statistics tracking.
 *
 * @package Contextify\LaravelResourceOptimizer\Services
 */
class ResourceCache
{
    /**
     * Cache statistics storage.
     *
     * @var array<string, mixed>
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'stores' => 0,
        'failures' => 0,
    ];

    /**
     * Get cached data with statistics tracking.
     *
     * @param string $key
     * @param string|null $store
     * @return mixed
     */
    public function get(string $key, ?string $store = null)
    {
        try {
            $cache = $store ? Cache::store($store) : Cache::store();
            $result = $cache->get($key);

            if ($result !== null) {
                $this->stats['hits']++;
            } else {
                $this->stats['misses']++;
            }

            return $result;
        } catch (\Exception $e) {
            $this->stats['failures']++;
            return null;
        }
    }

    /**
     * Store data in cache with statistics tracking.
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @param string|null $store
     * @return bool
     */
    public function put(string $key, $data, int $ttl, ?string $store = null): bool
    {
        try {
            $cache = $store ? Cache::store($store) : Cache::store();
            $result = $cache->put($key, $data, $ttl);

            if ($result) {
                $this->stats['stores']++;
            } else {
                $this->stats['failures']++;
            }

            return $result;
        } catch (\Exception $e) {
            $this->stats['failures']++;
            return false;
        }
    }

    /**
     * Get cache statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;

        return array_merge($this->stats, [
            'hit_rate' => round($hitRate, 2),
            'total_requests' => $total,
        ]);
    }

    /**
     * Clear cache statistics.
     *
     * @return void
     */
    public function clearStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'stores' => 0,
            'failures' => 0,
        ];
    }
}