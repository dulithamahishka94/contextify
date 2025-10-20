<?php

namespace Contextify\LaravelResourceContext;

use Illuminate\Http\Resources\Json\JsonResource;
use Contextify\LaravelResourceContext\Traits\ResourceContext;

/**
 * Class ContextualResource
 *
 * Extended JSON Resource class that provides context propagation functionality.
 * This class allows nested resources to access parent resource attributes
 * without additional database queries, with configurable attribute precedence
 * for handling naming conflicts.
 *
 * Key features:
 * - Automatic context propagation to child resources
 * - Configurable attribute precedence (priority attributes)
 * - Memory-efficient implementation
 * - Compatible with all Laravel resource features
 *
 * @package Contextify\LaravelResourceContext
 */
class ContextualResource extends JsonResource
{
    use ResourceContext;

    /**
     * Array of attribute names that should prioritize parent context
     * over local resource attributes when using getContextualAttribute().
     *
     * @var array<int, string>
     */
    protected array $priorityAttributes = [];

    /**
     * Set multiple attributes to use priority-first resolution.
     *
     * Configures which attributes should prioritize parent context values
     * over local resource values when using getContextualAttribute().
     * Replaces any previously configured priority attributes.
     *
     * @param array<int, string> $attributes Array of attribute names to prioritize
     * @return $this
     */
    public function setPriorityAttributes(array $attributes): self
    {
        $this->priorityAttributes = $attributes;
        return $this;
    }

    /**
     * Get the current list of priority attributes.
     *
     * Returns all attribute names that are configured to use
     * priority-first resolution in getContextualAttribute().
     *
     * @return array<int, string> Array of priority attribute names
     */
    public function getPriorityAttributes(): array
    {
        return $this->priorityAttributes;
    }

    /**
     * Add a single attribute to use priority-first resolution.
     *
     * Adds an attribute to the priority list if it's not already present.
     * This attribute will prioritize parent context values over local
     * resource values when using getContextualAttribute().
     *
     * @param string $attribute The attribute name to prioritize
     * @return $this
     */
    public function usePriorityForAttribute(string $attribute): self
    {
        if (!in_array($attribute, $this->priorityAttributes)) {
            $this->priorityAttributes[] = $attribute;
        }
        return $this;
    }

    /**
     * Transform the resource into an array with context propagation.
     *
     * This method overrides Laravel's default toArray() to provide automatic
     * context propagation. It pushes the current resource context, transforms
     * the resource, processes nested resources for context propagation, and
     * ensures proper cleanup via try-finally.
     *
     * The transformation flow:
     * 1. Push current resource context to the stack
     * 2. Call transformResource() for the actual transformation logic
     * 3. Process nested resources to propagate context
     * 4. Clean up context stack
     *
     * @param \Illuminate\Http\Request|null $request The HTTP request instance
     * @return array<string, mixed> The transformed resource as array
     */
    public function toArray($request = null)
    {
        $this->pushContext();

        try {
            $result = $this->transformResource($request);

            if (is_array($result)) {
                $result = $this->processNestedResources($result);
            }

            return $result;
        } finally {
            $this->popContext();
        }
    }

    /**
     * Transform the resource data (override this method in your resource classes).
     *
     * This method should be overridden in your resource classes instead of toArray().
     * It provides the actual transformation logic while toArray() handles the
     * context propagation infrastructure.
     *
     * By default, this calls the parent JsonResource's toArray() method.
     *
     * @param \Illuminate\Http\Request|null $request The HTTP request instance
     * @return mixed The transformed resource data
     */
    protected function transformResource($request)
    {
        return parent::toArray($request);
    }

    /**
     * Process nested resources to propagate context.
     *
     * Iterates through the resource array and applies context propagation
     * to any nested resources. This ensures that child resources receive
     * the parent context automatically.
     *
     * @param array<string, mixed> $data The resource data array
     * @return array<string, mixed> The processed data with context propagated
     */
    protected function processNestedResources(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->propagateContextToResource($value);
        }

        return $data;
    }

    /**
     * Include a value conditionally with context propagation.
     *
     * Extends Laravel's when() method to automatically propagate context
     * to the conditional value. This ensures that nested resources included
     * conditionally still receive the parent context.
     *
     * @param bool $condition The condition to evaluate
     * @param mixed $value The value to include if condition is true
     * @param mixed $default The value to include if condition is false
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function when($condition, $value = null, $default = null)
    {
        if ($condition) {
            $value = $this->propagateContextToResource($value);
        } else {
            $value = $this->propagateContextToResource($default);
        }

        return parent::when($condition, $value, $default);
    }

    /**
     * Include a relationship conditionally with context propagation.
     *
     * Extends Laravel's whenLoaded() method to automatically propagate context
     * to the relationship data. This ensures that related resources receive
     * the parent context when the relationship is loaded.
     *
     * @param string $relationship The name of the relationship to check
     * @param mixed $value The value to include if relationship is loaded
     * @param mixed $default The value to include if relationship is not loaded
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function whenLoaded($relationship, $value = null, $default = null)
    {
        if ($this->resource && $this->relationLoaded($relationship)) {
            $value = $this->propagateContextToResource($value ?? $this->resource->{$relationship});
        } else {
            $value = $this->propagateContextToResource($default);
        }

        return parent::whenLoaded($relationship, $value, $default);
    }

    /**
     * Merge attributes conditionally with context propagation.
     *
     * Extends Laravel's mergeWhen() method to automatically propagate context
     * to the merged attributes. Handles both array and resource values,
     * ensuring context is properly propagated to nested resources.
     *
     * @param bool $condition The condition to evaluate
     * @param mixed $value The value to merge if condition is true
     * @param mixed $default The default value if condition is false
     * @return $this
     */
    public function mergeWhen($condition, $value, $default = null)
    {
        if (is_array($value)) {
            $value = $this->processNestedResources($value);
        } else {
            $value = $this->propagateContextToResource($value);
        }

        return parent::mergeWhen($condition, $value, $default);
    }

    /**
     * Check if a relationship is loaded on the resource.
     *
     * Determines whether a given relationship has been loaded on the underlying
     * Eloquent model. Supports both Eloquent models (via relationLoaded method)
     * and generic objects (via property_exists check).
     *
     * @param string $relationship The name of the relationship to check
     * @return bool True if the relationship is loaded, false otherwise
     */
    protected function relationLoaded($relationship): bool
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
     * Create a resource collection with context propagation support.
     *
     * Extends Laravel's collection() method to ensure that resource collections
     * maintain compatibility with the context propagation system. Preserves
     * the original Laravel behavior while supporting contextual resources.
     *
     * @param mixed $resource The collection of resources to wrap
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public static function collection($resource)
    {
        return tap(new static::$collects($resource), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }
}