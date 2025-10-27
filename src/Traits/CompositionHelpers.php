<?php

namespace Contextify\LaravelResourceOptimizer\Traits;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/**
 * Trait CompositionHelpers
 *
 * Provides advanced composition and transformation helpers for Laravel resources.
 * Includes utilities for conditional loading, batch operations, resource validation,
 * and improved resource composition patterns.
 *
 * @package Contextify\LaravelResourceOptimizer\Traits
 */
trait CompositionHelpers
{
    /**
     * Conditionally include a resource with optimized loading.
     *
     * @param bool $condition Condition to evaluate
     * @param callable $callback Callback that returns the resource
     * @param mixed $default Default value if condition is false
     * @return mixed
     */
    public function whenOptimized(bool $condition, callable $callback, $default = null)
    {
        if (!$condition) {
            return $this->when(false, $default);
        }

        try {
            $result = $callback();
            return $this->when(true, $result);
        } catch (\Exception $e) {
            \Log::warning("Error in whenOptimized callback", [
                'resource_class' => static::class,
                'error' => $e->getMessage(),
            ]);

            return $this->when(false, $default);
        }
    }

    /**
     * Include a relationship with automatic eager loading validation.
     *
     * @param string $relationship Relationship name
     * @param string|callable|null $resourceClass Resource class or callback
     * @param mixed $default Default value if relationship not loaded
     * @return mixed
     */
    public function whenLoadedOptimized(string $relationship, $resourceClass = null, $default = null)
    {
        if (!$this->relationLoaded($relationship)) {
            $this->logMissingRelationship($relationship);
            return $this->when(false, $default);
        }

        $relationshipData = $this->resource->{$relationship};

        if (is_null($relationshipData)) {
            return $this->when(true, null);
        }

        if (is_callable($resourceClass)) {
            return $this->when(true, $resourceClass($relationshipData));
        }

        if (is_string($resourceClass)) {
            if ($relationshipData instanceof Collection || is_array($relationshipData)) {
                return $this->when(true, $resourceClass::collection($relationshipData));
            }

            return $this->when(true, new $resourceClass($relationshipData));
        }

        return $this->when(true, $relationshipData);
    }

    /**
     * Create a resource collection with batch optimization.
     *
     * @param Collection|array $collection The collection to transform
     * @param string $resourceClass Resource class to use
     * @param array $options Additional options
     * @return ResourceCollection
     */
    public function optimizedCollection($collection, string $resourceClass, array $options = []): ResourceCollection
    {
        // Pre-load any specified relationships for the entire collection
        if (isset($options['with']) && $collection instanceof Collection) {
            $collection = $this->batchLoadRelationships($collection, $options['with']);
        }

        // Create the resource collection
        $resourceCollection = $resourceClass::collection($collection);

        // Apply batch optimizations if specified
        if (isset($options['cache_ttl'])) {
            $resourceCollection->additional(['cache_ttl' => $options['cache_ttl']]);
        }

        return $resourceCollection;
    }

    /**
     * Batch load relationships for a collection to prevent N+1 queries.
     *
     * @param Collection $collection The collection of models
     * @param array $relationships Relationships to load
     * @return Collection The collection with loaded relationships
     */
    protected function batchLoadRelationships(Collection $collection, array $relationships): Collection
    {
        if ($collection->isEmpty()) {
            return $collection;
        }

        $firstItem = $collection->first();

        // Only works with Eloquent models
        if (!is_object($firstItem) || !method_exists($firstItem, 'load')) {
            return $collection;
        }

        try {
            $collection->load($relationships);
        } catch (\Exception $e) {
            \Log::warning("Failed to batch load relationships", [
                'relationships' => $relationships,
                'model_class' => get_class($firstItem),
                'error' => $e->getMessage(),
            ]);
        }

        return $collection;
    }

    /**
     * Include multiple conditional resources in a single operation.
     *
     * @param array $conditionals Array of condition => resource pairs
     * @return array<string, mixed>
     */
    public function whenMultiple(array $conditionals): array
    {
        $result = [];

        foreach ($conditionals as $key => $conditional) {
            if (is_array($conditional) && count($conditional) === 2) {
                [$condition, $resource] = $conditional;
                $result[$key] = $this->when($condition, $resource);
            } elseif (is_callable($conditional)) {
                $result[$key] = $conditional();
            }
        }

        return $result;
    }

    /**
     * Merge multiple resource transformations with conflict resolution.
     *
     * @param array $resources Array of resources to merge
     * @param array $options Merge options
     * @return array<string, mixed>
     */
    public function mergeResources(array $resources, array $options = []): array
    {
        $merged = [];
        $strategy = $options['strategy'] ?? 'last_wins';

        foreach ($resources as $resource) {
            $transformed = $this->transformResourceData($resource);

            switch ($strategy) {
                case 'first_wins':
                    $merged = array_merge($transformed, $merged);
                    break;
                case 'last_wins':
                default:
                    $merged = array_merge($merged, $transformed);
                    break;
                case 'deep_merge':
                    $merged = $this->deepMergeArrays($merged, $transformed);
                    break;
            }
        }

        return $merged;
    }

    /**
     * Transform resource data to array format.
     *
     * @param mixed $resource Resource to transform
     * @return array<string, mixed>
     */
    protected function transformResourceData($resource): array
    {
        if ($resource instanceof JsonResource) {
            return $resource->toArray(request());
        }

        if (is_array($resource)) {
            return $resource;
        }

        if (is_object($resource) && method_exists($resource, 'toArray')) {
            return $resource->toArray();
        }

        return ['data' => $resource];
    }

    /**
     * Deep merge two arrays recursively.
     *
     * @param array $array1 First array
     * @param array $array2 Second array
     * @return array<string, mixed> Merged array
     */
    protected function deepMergeArrays(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key]) && is_array($array1[$key]) && is_array($value)) {
                $array1[$key] = $this->deepMergeArrays($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    /**
     * Validate resource data consistency.
     *
     * @param array $data Resource data to validate
     * @param array $rules Validation rules
     * @return array<string, mixed> Validation result
     */
    public function validateResourceData(array $data, array $rules = []): array
    {
        $violations = [];

        // Default validation rules
        $defaultRules = [
            'no_null_required_fields' => true,
            'no_empty_collections' => false,
            'consistent_id_format' => true,
        ];

        $rules = array_merge($defaultRules, $rules);

        if ($rules['no_null_required_fields']) {
            $violations = array_merge($violations, $this->checkNullFields($data));
        }

        if ($rules['no_empty_collections']) {
            $violations = array_merge($violations, $this->checkEmptyCollections($data));
        }

        if ($rules['consistent_id_format']) {
            $violations = array_merge($violations, $this->checkIdConsistency($data));
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'data' => $data,
        ];
    }

    /**
     * Check for null values in required fields.
     *
     * @param array $data Data to check
     * @return array<string> Violations found
     */
    protected function checkNullFields(array $data): array
    {
        $violations = [];
        $requiredFields = ['id']; // Add more as needed

        foreach ($requiredFields as $field) {
            if (array_key_exists($field, $data) && is_null($data[$field])) {
                $violations[] = "Required field '{$field}' is null";
            }
        }

        return $violations;
    }

    /**
     * Check for empty collections that might indicate missing data.
     *
     * @param array $data Data to check
     * @return array<string> Violations found
     */
    protected function checkEmptyCollections(array $data): array
    {
        $violations = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && empty($value) && str_ends_with($key, 's')) {
                $violations[] = "Collection '{$key}' is empty";
            }
        }

        return $violations;
    }

    /**
     * Check for consistent ID formatting across the resource.
     *
     * @param array $data Data to check
     * @return array<string> Violations found
     */
    protected function checkIdConsistency(array $data): array
    {
        $violations = [];
        $idFields = [];

        // Collect all ID fields
        foreach ($data as $key => $value) {
            if (str_ends_with($key, '_id') || $key === 'id') {
                $idFields[$key] = $value;
            }
        }

        // Check consistency (all numeric or all string UUIDs, etc.)
        if (count($idFields) > 1) {
            $types = array_map('gettype', $idFields);
            $uniqueTypes = array_unique($types);

            if (count($uniqueTypes) > 1) {
                $violations[] = "Inconsistent ID field types: " . implode(', ', $uniqueTypes);
            }
        }

        return $violations;
    }

    /**
     * Log missing relationship for debugging purposes.
     *
     * @param string $relationship Missing relationship name
     * @return void
     */
    protected function logMissingRelationship(string $relationship): void
    {
        if (config('resource-optimizer.debug_mode', false)) {
            \Log::info("Missing relationship detected", [
                'resource_class' => static::class,
                'relationship' => $relationship,
                'suggestion' => "Consider eager loading with ->with(['{$relationship}'])",
            ]);
        }
    }

    /**
     * Create a partial resource with only specified fields.
     *
     * @param array $fields Fields to include
     * @param array $aliases Field aliases (old_name => new_name)
     * @return array<string, mixed>
     */
    public function partial(array $fields, array $aliases = []): array
    {
        $result = [];
        $data = $this->transformResource(request());

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $outputKey = $aliases[$field] ?? $field;
                $result[$outputKey] = $data[$field];
            }
        }

        return $result;
    }

    /**
     * Create a resource summary with computed fields.
     *
     * @param array $computedFields Computed field definitions
     * @return array<string, mixed>
     */
    public function summary(array $computedFields = []): array
    {
        $data = $this->transformResource(request());
        $summary = [];

        // Include basic fields
        $basicFields = ['id', 'name', 'title', 'created_at', 'updated_at'];
        foreach ($basicFields as $field) {
            if (array_key_exists($field, $data)) {
                $summary[$field] = $data[$field];
            }
        }

        // Add computed fields
        foreach ($computedFields as $field => $callback) {
            if (is_callable($callback)) {
                $summary[$field] = $callback($data, $this->resource);
            }
        }

        return $summary;
    }
}