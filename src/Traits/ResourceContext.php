<?php

namespace Contextify\LaravelResourceContext\Traits;

/**
 * Trait ResourceContext
 *
 * Provides context propagation functionality for Laravel JSON resources,
 * allowing child resources to access parent resource attributes without
 * additional database queries. Supports configurable attribute precedence
 * for handling naming conflicts between parent and child resources.
 *
 * @package Contextify\LaravelResourceContext\Traits
 */
trait ResourceContext
{
    /**
     * Global context stack that maintains parent resource attributes
     * throughout the nested resource transformation chain.
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $contextStack = [];

    /**
     * Regular parent context attributes with medium priority.
     * These are merged from all parent resources in the chain.
     *
     * @var array<string, mixed>
     */
    protected array $parentAttributes = [];

    /**
     * High-priority parent context attributes that override
     * both regular parent context and local resource attributes
     * when configured as priority attributes.
     *
     * @var array<string, mixed>
     */
    protected array $priorityParentAttributes = [];

    /**
     * Add attributes to the regular parent context.
     *
     * These attributes will be available to child resources through
     * getParentAttribute() and getContextualAttribute() methods.
     * Has medium priority in the attribute resolution hierarchy.
     *
     * @param array<string, mixed> $context The context attributes to add
     * @return $this
     */
    public function withContext(array $context): self
    {
        $this->parentAttributes = array_merge($this->parentAttributes, $context);
        return $this;
    }

    /**
     * Get a specific attribute from the parent context.
     *
     * Retrieves an attribute value from the regular parent context.
     * This does not check priority context or local resource attributes.
     *
     * @param string $key The attribute key to retrieve
     * @param mixed $default The default value if the key is not found
     * @return mixed The attribute value or default
     */
    public function getParentAttribute(string $key, $default = null)
    {
        return $this->parentAttributes[$key] ?? $default;
    }

    /**
     * Check if a specific attribute exists in the parent context.
     *
     * Determines whether the given key exists in the regular parent context.
     * This does not check priority context or local resource attributes.
     *
     * @param string $key The attribute key to check
     * @return bool True if the key exists, false otherwise
     */
    public function hasParentAttribute(string $key): bool
    {
        return array_key_exists($key, $this->parentAttributes);
    }

    /**
     * Get all attributes from the regular parent context.
     *
     * Returns the complete array of parent context attributes.
     * This does not include priority context attributes.
     *
     * @return array<string, mixed> All parent context attributes
     */
    public function getAllParentAttributes(): array
    {
        return $this->parentAttributes;
    }

    /**
     * Add attributes to the high-priority parent context.
     *
     * These attributes will override regular parent context and local
     * resource attributes when the attribute is configured as a priority
     * attribute using setPriorityAttributes() or usePriorityForAttribute().
     *
     * @param array<string, mixed> $context The priority context attributes to add
     * @return $this
     */
    public function withPriorityContext(array $context): self
    {
        $this->priorityParentAttributes = array_merge($this->priorityParentAttributes, $context);
        return $this;
    }

    /**
     * Get an attribute exclusively from higher-level contexts.
     *
     * Checks priority context first, then regular parent context.
     * Never checks the local resource attributes, ensuring you always
     * get the value from a parent resource.
     *
     * @param string $key The attribute key to retrieve
     * @param mixed $default The default value if the key is not found
     * @return mixed The attribute value from higher-level context or default
     */
    public function getHigherLevelAttribute(string $key, $default = null)
    {
        if (array_key_exists($key, $this->priorityParentAttributes)) {
            return $this->priorityParentAttributes[$key];
        }

        if ($this->hasParentAttribute($key)) {
            return $this->getParentAttribute($key);
        }

        return $default;
    }

    /**
     * Get an attribute with intelligent fallback behavior.
     *
     * This is the main method for retrieving attributes with configurable precedence.
     * The resolution order depends on whether the attribute is configured as a priority
     * attribute:
     *
     * For priority attributes:
     * 1. Priority context (highest priority)
     * 2. Regular parent context
     * 3. Never checks local resource
     *
     * For non-priority attributes:
     * 1. Local resource attribute
     * 2. Priority context (fallback)
     * 3. Regular parent context (final fallback)
     *
     * @param string $key The attribute key to retrieve
     * @param mixed $default The default value if the key is not found anywhere
     * @return mixed The attribute value based on configured precedence or default
     */
    public function getContextualAttribute(string $key, $default = null)
    {
        $usePriority = $this->shouldUsePriorityForAttribute($key);

        if ($usePriority && array_key_exists($key, $this->priorityParentAttributes)) {
            return $this->priorityParentAttributes[$key];
        }

        if ($usePriority && $this->hasParentAttribute($key)) {
            return $this->getParentAttribute($key);
        }

        if ($this->resource && is_object($this->resource) && property_exists($this->resource, $key)) {
            return $this->resource->{$key};
        }

        if ($this->resource && is_array($this->resource) && array_key_exists($key, $this->resource)) {
            return $this->resource[$key];
        }

        if (!$usePriority && array_key_exists($key, $this->priorityParentAttributes)) {
            return $this->priorityParentAttributes[$key];
        }

        if (!$usePriority && $this->hasParentAttribute($key)) {
            return $this->getParentAttribute($key);
        }

        return $default;
    }

    /**
     * Check if an attribute should use priority-first resolution.
     *
     * Determines whether the given attribute key is configured to prioritize
     * parent context over local resource attributes. This is used internally
     * by getContextualAttribute() to determine resolution order.
     *
     * @param string $key The attribute key to check
     * @return bool True if the attribute should use priority resolution
     */
    protected function shouldUsePriorityForAttribute(string $key): bool
    {
        if (method_exists($this, 'getPriorityAttributes')) {
            return in_array($key, $this->getPriorityAttributes());
        }
        return false;
    }

    /**
     * Get an attribute exclusively from the local resource.
     *
     * Retrieves an attribute value only from the current resource,
     * ignoring all parent contexts. Useful when you specifically
     * need the local resource's value.
     *
     * @param string $key The attribute key to retrieve
     * @param mixed $default The default value if the key is not found
     * @return mixed The attribute value from local resource or default
     */
    public function getResourceAttribute(string $key, $default = null)
    {
        if ($this->resource && is_object($this->resource) && property_exists($this->resource, $key)) {
            return $this->resource->{$key};
        }

        if ($this->resource && is_array($this->resource) && array_key_exists($key, $this->resource)) {
            return $this->resource[$key];
        }

        return $default;
    }

    /**
     * Push the current resource context onto the global context stack.
     *
     * This method is called automatically during resource transformation
     * to make the current resource's attributes available to child resources.
     * Combines parent contexts with the current resource's attributes.
     *
     * @return void
     */
    protected function pushContext(): void
    {
        $currentContext = array_merge($this->parentAttributes, $this->priorityParentAttributes);

        if ($this->resource) {
            if (is_object($this->resource)) {
                $resourceAttributes = get_object_vars($this->resource);
                if (method_exists($this->resource, 'toArray')) {
                    $resourceAttributes = array_merge($resourceAttributes, $this->resource->toArray());
                }
            } elseif (is_array($this->resource)) {
                $resourceAttributes = $this->resource;
            } else {
                $resourceAttributes = [];
            }

            $currentContext = array_merge($currentContext, $resourceAttributes);
        }

        static::$contextStack[] = $currentContext;
    }

    /**
     * Remove the current context from the global context stack.
     *
     * This method is called automatically after resource transformation
     * to clean up the context stack. Should always be called in a
     * try-finally block to ensure proper cleanup.
     *
     * @return void
     */
    protected function popContext(): void
    {
        if (!empty(static::$contextStack)) {
            array_pop(static::$contextStack);
        }
    }

    /**
     * Get the current context from the top of the context stack.
     *
     * Returns the most recent context that was pushed onto the stack.
     * Used internally for propagating context to child resources.
     *
     * @return array<string, mixed> The current context or empty array if stack is empty
     */
    protected function getCurrentContext(): array
    {
        return end(static::$contextStack) ?: [];
    }

    /**
     * Propagate context to a child resource and transform it to array.
     *
     * This method handles the automatic propagation of both regular and priority
     * context to child resources. It supports single resources, arrays, and
     * collections. After setting the context, it transforms the resource(s)
     * to array format for final output.
     *
     * Context propagation flow:
     * 1. Regular context (withContext) - medium priority data
     * 2. Priority context (withPriorityContext) - high priority data
     * 3. Transform resource to array via toArray()
     *
     * @param mixed $resource The resource to propagate context to (JsonResource, array, Collection, or null)
     * @return mixed The transformed resource as array or original value if not a resource
     */
    protected function propagateContextToResource($resource)
    {
        if (is_null($resource)) {
            return $resource;
        }

        $currentContext = $this->getCurrentContext();
        $priorityContext = $this->priorityParentAttributes;

        if ($resource instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            if (method_exists($resource, 'withContext')) {
                $resource = $resource->withContext($currentContext);
            }
            if (method_exists($resource, 'withPriorityContext') && !empty($priorityContext)) {
                $resource = $resource->withPriorityContext($priorityContext);
            }
            return $resource->toArray(request());
        }

        if (is_array($resource) || $resource instanceof \Illuminate\Support\Collection) {
            return collect($resource)->map(function ($item) use ($currentContext, $priorityContext) {
                if ($item instanceof \Illuminate\Http\Resources\Json\JsonResource) {
                    if (method_exists($item, 'withContext')) {
                        $item = $item->withContext($currentContext);
                    }
                    if (method_exists($item, 'withPriorityContext') && !empty($priorityContext)) {
                        $item = $item->withPriorityContext($priorityContext);
                    }
                    return $item->toArray(request());
                }
                return $item;
            })->toArray();
        }

        return $resource;
    }
}