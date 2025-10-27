<?php

namespace Contextify\LaravelResourceOptimizer\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait QueryDetection
 *
 * Provides N+1 query detection capabilities for Laravel resources.
 * Automatically detects when resource transformations trigger excessive
 * database queries and provides warnings and suggestions for optimization.
 *
 * @package Contextify\LaravelResourceOptimizer\Traits
 */
trait QueryDetection
{
    /**
     * Query count before resource transformation starts.
     *
     * @var int
     */
    protected int $initialQueryCount = 0;

    /**
     * Queries executed during resource transformation.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $executedQueries = [];

    /**
     * Whether query detection is currently active.
     *
     * @var bool
     */
    protected bool $queryDetectionActive = false;

    /**
     * Start monitoring database queries for N+1 detection.
     *
     * @return void
     */
    protected function startQueryDetection(): void
    {
        if (!$this->detectQueries) {
            return;
        }

        $this->queryDetectionActive = true;
        $this->initialQueryCount = $this->getCurrentQueryCount();
        $this->executedQueries = [];

        DB::listen(function ($query) {
            if ($this->queryDetectionActive) {
                $this->executedQueries[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                    'connection' => $query->connectionName,
                ];
            }
        });
    }

    /**
     * Stop monitoring queries and analyze for N+1 patterns.
     *
     * @return void
     */
    protected function stopQueryDetection(): void
    {
        if (!$this->queryDetectionActive) {
            return;
        }

        $this->queryDetectionActive = false;
        $this->analyzeQueryPatterns();
    }

    /**
     * Analyze executed queries for N+1 patterns and performance issues.
     *
     * @return void
     */
    protected function analyzeQueryPatterns(): void
    {
        $queryCount = count($this->executedQueries);
        $threshold = config('resource-optimizer.query_detection.threshold', 5);

        if ($queryCount > $threshold) {
            $this->handlePotentialNPlusOne($queryCount);
        }

        $this->detectDuplicateQueries();
        $this->detectSlowQueries();
        $this->suggestEagerLoading();
    }

    /**
     * Handle detection of potential N+1 query patterns.
     *
     * @param int $queryCount Number of queries executed
     * @return void
     */
    protected function handlePotentialNPlusOne(int $queryCount): void
    {
        $resourceClass = static::class;
        $resourceId = $this->resourceId ?? 'unknown';

        $message = "Potential N+1 query detected in {$resourceClass} (ID: {$resourceId}). " .
                   "Executed {$queryCount} queries during transformation.";

        // Log the warning
        Log::warning($message, [
            'resource_class' => $resourceClass,
            'resource_id' => $resourceId,
            'query_count' => $queryCount,
            'queries' => $this->executedQueries,
        ]);

        // Add to debug collection if in debug mode
        if (config('resource-optimizer.debug_mode', false)) {
            $this->addToDebugReport('n_plus_one_warning', [
                'message' => $message,
                'query_count' => $queryCount,
                'queries' => $this->executedQueries,
            ]);
        }
    }

    /**
     * Detect duplicate queries that could be optimized.
     *
     * @return void
     */
    protected function detectDuplicateQueries(): void
    {
        $queryHashes = [];
        $duplicates = [];

        foreach ($this->executedQueries as $query) {
            $hash = $this->normalizeQuery($query['sql']);

            if (isset($queryHashes[$hash])) {
                $duplicates[$hash] = ($duplicates[$hash] ?? 1) + 1;
            } else {
                $queryHashes[$hash] = true;
            }
        }

        foreach ($duplicates as $queryHash => $count) {
            if ($count > 2) {
                Log::info("Duplicate query detected: executed {$count} times", [
                    'resource_class' => static::class,
                    'query_pattern' => $queryHash,
                    'count' => $count,
                ]);
            }
        }
    }

    /**
     * Detect slow queries that could impact performance.
     *
     * @return void
     */
    protected function detectSlowQueries(): void
    {
        $slowThreshold = config('resource-optimizer.query_detection.slow_threshold', 100);

        foreach ($this->executedQueries as $query) {
            if ($query['time'] > $slowThreshold) {
                Log::warning("Slow query detected in resource transformation", [
                    'resource_class' => static::class,
                    'sql' => $query['sql'],
                    'time' => $query['time'],
                    'bindings' => $query['bindings'],
                ]);
            }
        }
    }

    /**
     * Suggest eager loading optimizations based on query patterns.
     *
     * @return void
     */
    protected function suggestEagerLoading(): void
    {
        if (!is_object($this->resource) || !method_exists($this->resource, 'getTable')) {
            return;
        }

        $suggestions = $this->analyzeRelationshipQueries();

        if (!empty($suggestions)) {
            Log::info("Eager loading suggestions for " . static::class, [
                'suggestions' => $suggestions,
                'resource_class' => static::class,
            ]);

            if (config('resource-optimizer.debug_mode', false)) {
                $this->addToDebugReport('eager_loading_suggestions', $suggestions);
            }
        }
    }

    /**
     * Analyze queries to suggest relationship eager loading.
     *
     * @return array<string> Array of suggested relationships to eager load
     */
    protected function analyzeRelationshipQueries(): array
    {
        $suggestions = [];
        $tableName = $this->resource->getTable();

        foreach ($this->executedQueries as $query) {
            $sql = $query['sql'];

            // Look for foreign key queries that could be eager loaded
            if (preg_match('/select.*from [`"`]?(\w+)[`"`]?.*where [`"`]?\w+_id[`"`]? in \(/i', $sql, $matches)) {
                $relatedTable = $matches[1];

                if ($relatedTable !== $tableName) {
                    $suggestions[] = $this->guessRelationshipName($relatedTable);
                }
            }
        }

        return array_unique(array_filter($suggestions));
    }

    /**
     * Guess relationship name from table name.
     *
     * @param string $tableName The related table name
     * @return string|null Guessed relationship name
     */
    protected function guessRelationshipName(string $tableName): ?string
    {
        // Simple conversion from table name to relationship name
        // Remove common table suffixes and convert to camelCase
        $cleaned = preg_replace('/s$/', '', $tableName); // Remove trailing 's'
        return str_replace('_', '', ucwords($cleaned, '_'));
    }

    /**
     * Normalize a SQL query for duplicate detection.
     *
     * @param string $sql The SQL query
     * @return string Normalized query pattern
     */
    protected function normalizeQuery(string $sql): string
    {
        // Replace dynamic values with placeholders for pattern matching
        $normalized = preg_replace('/\b\d+\b/', '?', $sql);
        $normalized = preg_replace("/'[^']*'/", '?', $normalized);
        $normalized = preg_replace('/"[^"]*"/', '?', $normalized);

        return trim(preg_replace('/\s+/', ' ', $normalized));
    }

    /**
     * Get current database query count across all connections.
     *
     * @return int Current query count
     */
    protected function getCurrentQueryCount(): int
    {
        $count = 0;

        foreach (DB::getConnections() as $connection) {
            $count += $connection->getQueryLog() ? count($connection->getQueryLog()) : 0;
        }

        return $count;
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