<?php

namespace Contextify\LaravelResourceOptimizer\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Trait PerformanceMonitoring
 *
 * Provides comprehensive performance monitoring for Laravel resource transformations.
 * Tracks execution time, memory usage, and provides performance analytics to help
 * identify bottlenecks and optimization opportunities.
 *
 * @package Contextify\LaravelResourceOptimizer\Traits
 */
trait PerformanceMonitoring
{
    /**
     * Performance monitoring data for this resource instance.
     *
     * @var array<string, mixed>
     */
    protected array $performanceData = [];

    /**
     * Global performance statistics across all resources.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $globalPerformanceStats = [];

    /**
     * Start performance monitoring for resource transformation.
     *
     * @return void
     */
    protected function startPerformanceMonitoring(): void
    {
        if (!$this->monitorPerformance) {
            return;
        }

        $this->performanceData = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'resource_class' => static::class,
            'resource_id' => $this->resourceId ?? 'unknown',
        ];
    }

    /**
     * Stop performance monitoring and record metrics.
     *
     * @return void
     */
    protected function stopPerformanceMonitoring(): void
    {
        if (!$this->monitorPerformance || empty($this->performanceData)) {
            return;
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $this->performanceData['end_time'] = $endTime;
        $this->performanceData['end_memory'] = $endMemory;
        $this->performanceData['end_peak_memory'] = $endPeakMemory;

        // Calculate metrics
        $this->performanceData['execution_time'] = $endTime - $this->performanceData['start_time'];
        $this->performanceData['memory_used'] = $endMemory - $this->performanceData['start_memory'];
        $this->performanceData['peak_memory_increase'] = $endPeakMemory - $this->performanceData['start_peak_memory'];

        $this->recordPerformanceMetrics();
        $this->analyzePerformance();
    }

    /**
     * Record performance metrics for analysis and reporting.
     *
     * @return void
     */
    protected function recordPerformanceMetrics(): void
    {
        $className = static::class;

        // Initialize class stats if not exists
        if (!isset(static::$globalPerformanceStats[$className])) {
            static::$globalPerformanceStats[$className] = [
                'count' => 0,
                'total_time' => 0,
                'total_memory' => 0,
                'max_time' => 0,
                'max_memory' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'min_memory' => PHP_INT_MAX,
            ];
        }

        $stats = &static::$globalPerformanceStats[$className];
        $executionTime = $this->performanceData['execution_time'];
        $memoryUsed = $this->performanceData['memory_used'];

        // Update statistics
        $stats['count']++;
        $stats['total_time'] += $executionTime;
        $stats['total_memory'] += $memoryUsed;
        $stats['max_time'] = max($stats['max_time'], $executionTime);
        $stats['max_memory'] = max($stats['max_memory'], $memoryUsed);
        $stats['min_time'] = min($stats['min_time'], $executionTime);
        $stats['min_memory'] = min($stats['min_memory'], $memoryUsed);

        // Calculate averages
        $stats['avg_time'] = $stats['total_time'] / $stats['count'];
        $stats['avg_memory'] = $stats['total_memory'] / $stats['count'];
    }

    /**
     * Analyze performance and log warnings for slow transformations.
     *
     * @return void
     */
    protected function analyzePerformance(): void
    {
        $executionTime = $this->performanceData['execution_time'];
        $memoryUsed = $this->performanceData['memory_used'];

        $slowThreshold = config('resource-optimizer.performance.slow_threshold', 0.1); // 100ms
        $memoryThreshold = config('resource-optimizer.performance.memory_threshold', 10 * 1024 * 1024); // 10MB

        $warnings = [];

        if ($executionTime > $slowThreshold) {
            $warnings[] = "Slow transformation: {$executionTime}s (threshold: {$slowThreshold}s)";
        }

        if ($memoryUsed > $memoryThreshold) {
            $warnings[] = "High memory usage: " . $this->formatBytes($memoryUsed) .
                         " (threshold: " . $this->formatBytes($memoryThreshold) . ")";
        }

        if (!empty($warnings)) {
            Log::warning("Performance warning for " . static::class, [
                'resource_class' => static::class,
                'resource_id' => $this->resourceId,
                'warnings' => $warnings,
                'metrics' => $this->getPerformanceMetrics(),
            ]);
        }

        // Log detailed metrics in debug mode
        if (config('resource-optimizer.debug_mode', false)) {
            $this->addToDebugReport('performance_metrics', $this->getPerformanceMetrics());
        }
    }

    /**
     * Get performance metrics for this resource transformation.
     *
     * @return array<string, mixed> Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        if (empty($this->performanceData)) {
            return [];
        }

        return [
            'execution_time' => $this->performanceData['execution_time'] ?? 0,
            'execution_time_formatted' => $this->formatTime($this->performanceData['execution_time'] ?? 0),
            'memory_used' => $this->performanceData['memory_used'] ?? 0,
            'memory_used_formatted' => $this->formatBytes($this->performanceData['memory_used'] ?? 0),
            'peak_memory_increase' => $this->performanceData['peak_memory_increase'] ?? 0,
            'peak_memory_increase_formatted' => $this->formatBytes($this->performanceData['peak_memory_increase'] ?? 0),
            'resource_class' => static::class,
            'resource_id' => $this->resourceId ?? 'unknown',
        ];
    }

    /**
     * Get global performance statistics for all resource classes.
     *
     * @return array<string, array<string, mixed>> Global performance statistics
     */
    public static function getGlobalPerformanceStats(): array
    {
        $formattedStats = [];

        foreach (static::$globalPerformanceStats as $className => $stats) {
            $formattedStats[$className] = [
                'count' => $stats['count'],
                'avg_time' => $stats['avg_time'],
                'avg_time_formatted' => static::formatTime($stats['avg_time']),
                'max_time' => $stats['max_time'],
                'max_time_formatted' => static::formatTime($stats['max_time']),
                'min_time' => $stats['min_time'] === PHP_FLOAT_MAX ? 0 : $stats['min_time'],
                'min_time_formatted' => static::formatTime($stats['min_time'] === PHP_FLOAT_MAX ? 0 : $stats['min_time']),
                'avg_memory' => $stats['avg_memory'],
                'avg_memory_formatted' => static::formatBytes($stats['avg_memory']),
                'max_memory' => $stats['max_memory'],
                'max_memory_formatted' => static::formatBytes($stats['max_memory']),
                'min_memory' => $stats['min_memory'] === PHP_INT_MAX ? 0 : $stats['min_memory'],
                'min_memory_formatted' => static::formatBytes($stats['min_memory'] === PHP_INT_MAX ? 0 : $stats['min_memory']),
                'total_time' => $stats['total_time'],
                'total_time_formatted' => static::formatTime($stats['total_time']),
                'total_memory' => $stats['total_memory'],
                'total_memory_formatted' => static::formatBytes($stats['total_memory']),
            ];
        }

        return $formattedStats;
    }

    /**
     * Reset global performance statistics.
     *
     * @return void
     */
    public static function resetGlobalPerformanceStats(): void
    {
        static::$globalPerformanceStats = [];
    }

    /**
     * Get performance report for the slowest resources.
     *
     * @param int $limit Number of slowest resources to return
     * @return array<string, mixed> Performance report
     */
    public static function getSlowResourcesReport(int $limit = 10): array
    {
        $stats = static::$globalPerformanceStats;

        // Sort by average execution time
        uasort($stats, function ($a, $b) {
            return $b['avg_time'] <=> $a['avg_time'];
        });

        return array_slice($stats, 0, $limit, true);
    }

    /**
     * Get performance report for the most memory-intensive resources.
     *
     * @param int $limit Number of memory-intensive resources to return
     * @return array<string, mixed> Performance report
     */
    public static function getMemoryIntensiveResourcesReport(int $limit = 10): array
    {
        $stats = static::$globalPerformanceStats;

        // Sort by average memory usage
        uasort($stats, function ($a, $b) {
            return $b['avg_memory'] <=> $a['avg_memory'];
        });

        return array_slice($stats, 0, $limit, true);
    }

    /**
     * Format execution time for human reading.
     *
     * @param float $seconds Execution time in seconds
     * @return string Formatted time string
     */
    protected static function formatTime(float $seconds): string
    {
        if ($seconds >= 1) {
            return number_format($seconds, 3) . 's';
        } elseif ($seconds >= 0.001) {
            return number_format($seconds * 1000, 2) . 'ms';
        } else {
            return number_format($seconds * 1000000, 2) . 'μs';
        }
    }

    /**
     * Format bytes for human reading.
     *
     * @param int $bytes Memory usage in bytes
     * @return string Formatted byte string
     */
    protected static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Add information to debug report collection.
     *
     * @param string $type Type of debug information
     * @param mixed $data Debug data
     * @return void
     */
    protected function addToDebugReport(string $type, $data): void
    {
        if (!isset($GLOBALS['resource_optimizer_debug'])) {
            $GLOBALS['resource_optimizer_debug'] = [];
        }

        $GLOBALS['resource_optimizer_debug'][] = [
            'type' => $type,
            'resource_class' => static::class,
            'resource_id' => $this->resourceId ?? 'unknown',
            'timestamp' => microtime(true),
            'data' => $data,
        ];
    }
}