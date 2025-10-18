<?php

namespace Contextify\LaravelResourceContext\Traits;

trait ResourceContext
{
    protected static array $contextStack = [];
    protected array $parentAttributes = [];
    protected array $priorityParentAttributes = [];

    public function withContext(array $context): self
    {
        $this->parentAttributes = array_merge($this->parentAttributes, $context);
        return $this;
    }

    public function getParentAttribute(string $key, $default = null)
    {
        return $this->parentAttributes[$key] ?? $default;
    }

    public function hasParentAttribute(string $key): bool
    {
        return array_key_exists($key, $this->parentAttributes);
    }

    public function getAllParentAttributes(): array
    {
        return $this->parentAttributes;
    }

    public function withPriorityContext(array $context): self
    {
        $this->priorityParentAttributes = array_merge($this->priorityParentAttributes, $context);
        return $this;
    }

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

    protected function shouldUsePriorityForAttribute(string $key): bool
    {
        if (method_exists($this, 'getPriorityAttributes')) {
            return in_array($key, $this->getPriorityAttributes());
        }
        return false;
    }

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

    protected function popContext(): void
    {
        if (!empty(static::$contextStack)) {
            array_pop(static::$contextStack);
        }
    }

    protected function getCurrentContext(): array
    {
        return end(static::$contextStack) ?: [];
    }

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