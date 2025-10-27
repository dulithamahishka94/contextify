<?php

namespace Contextify\LaravelResourceOptimizer\Services;

/**
 * Class QueryDetector
 *
 * Centralized N+1 query detection service for monitoring database
 * queries during resource transformations.
 *
 * @package Contextify\LaravelResourceOptimizer\Services
 */
class QueryDetector
{
    /**
     * Query detection results storage.
     *
     * @var array<string, mixed>
     */
    protected array $detectionResults = [];

    /**
     * Record query detection results for a resource transformation.
     *
     * @param string $resourceClass
     * @param array $results
     * @return void
     */
    public function recordDetection(string $resourceClass, array $results): void
    {
        if (!isset($this->detectionResults[$resourceClass])) {
            $this->detectionResults[$resourceClass] = [];
        }

        $this->detectionResults[$resourceClass][] = $results;
    }

    /**
     * Get detection results for a specific resource class.
     *
     * @param string $resourceClass
     * @return array
     */
    public function getResults(string $resourceClass): array
    {
        return $this->detectionResults[$resourceClass] ?? [];
    }

    /**
     * Get all detection results.
     *
     * @return array
     */
    public function getAllResults(): array
    {
        return $this->detectionResults;
    }

    /**
     * Clear all detection results.
     *
     * @return void
     */
    public function clearResults(): void
    {
        $this->detectionResults = [];
    }
}