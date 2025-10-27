<?php

namespace Contextify\LaravelResourceOptimizer\Services;

/**
 * Class PerformanceMonitor
 *
 * Centralized performance monitoring service for tracking resource
 * transformation metrics across the application.
 *
 * @package Contextify\LaravelResourceOptimizer\Services
 */
class PerformanceMonitor
{
    /**
     * Global performance metrics storage.
     *
     * @var array<string, mixed>
     */
    protected array $metrics = [];

    /**
     * Record performance metrics for a resource transformation.
     *
     * @param string $resourceClass
     * @param array $metrics
     * @return void
     */
    public function recordMetrics(string $resourceClass, array $metrics): void
    {
        if (!isset($this->metrics[$resourceClass])) {
            $this->metrics[$resourceClass] = [];
        }

        $this->metrics[$resourceClass][] = $metrics;
    }

    /**
     * Get performance metrics for a specific resource class.
     *
     * @param string $resourceClass
     * @return array
     */
    public function getMetrics(string $resourceClass): array
    {
        return $this->metrics[$resourceClass] ?? [];
    }

    /**
     * Get all performance metrics.
     *
     * @return array
     */
    public function getAllMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Clear all performance metrics.
     *
     * @return void
     */
    public function clearMetrics(): void
    {
        $this->metrics = [];
    }
}