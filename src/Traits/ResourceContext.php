<?php

namespace Contextify\LaravelResourceContext\Traits;

trait ResourceContext
{
    protected static array $contextStack = [];
    protected array $parentAttributes = [];

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

    public function getContextualAttribute(string $key, $default = null)
    {
        if ($this->hasParentAttribute($key)) {
            return $this->getParentAttribute($key);
        }

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
        $currentContext = $this->parentAttributes;

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

        if ($resource instanceof \Illuminate\Http\Resources\Json\JsonResource && method_exists($resource, 'withContext')) {
            return $resource->withContext($currentContext);
        }

        if (is_array($resource) || $resource instanceof \Illuminate\Support\Collection) {
            return collect($resource)->map(function ($item) use ($currentContext) {
                if ($item instanceof \Illuminate\Http\Resources\Json\JsonResource && method_exists($item, 'withContext')) {
                    return $item->withContext($currentContext);
                }
                return $item;
            });
        }

        return $resource;
    }
}