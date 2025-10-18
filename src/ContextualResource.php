<?php

namespace Contextify\LaravelResourceContext;

use Illuminate\Http\Resources\Json\JsonResource;
use Contextify\LaravelResourceContext\Traits\ResourceContext;

class ContextualResource extends JsonResource
{
    use ResourceContext;

    protected array $priorityAttributes = [];

    public function setPriorityAttributes(array $attributes): self
    {
        $this->priorityAttributes = $attributes;
        return $this;
    }

    public function getPriorityAttributes(): array
    {
        return $this->priorityAttributes;
    }

    public function usePriorityForAttribute(string $attribute): self
    {
        if (!in_array($attribute, $this->priorityAttributes)) {
            $this->priorityAttributes[] = $attribute;
        }
        return $this;
    }

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

    protected function transformResource($request)
    {
        return parent::toArray($request);
    }

    protected function processNestedResources(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->propagateContextToResource($value);
        }

        return $data;
    }

    public function when($condition, $value = null, $default = null)
    {
        if ($condition) {
            $value = $this->propagateContextToResource($value);
        } else {
            $value = $this->propagateContextToResource($default);
        }

        return parent::when($condition, $value, $default);
    }

    public function whenLoaded($relationship, $value = null, $default = null)
    {
        if ($this->resource && $this->relationLoaded($relationship)) {
            $value = $this->propagateContextToResource($value ?? $this->resource->{$relationship});
        } else {
            $value = $this->propagateContextToResource($default);
        }

        return parent::whenLoaded($relationship, $value, $default);
    }

    public function mergeWhen($condition, $value, $default = null)
    {
        if (is_array($value)) {
            $value = $this->processNestedResources($value);
        } else {
            $value = $this->propagateContextToResource($value);
        }

        return parent::mergeWhen($condition, $value, $default);
    }

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

    public static function collection($resource)
    {
        return tap(new static::$collects($resource), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }
}