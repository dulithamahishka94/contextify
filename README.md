# Laravel Resource Context

A Laravel package that allows nested resources to access parent resource attributes without additional database queries, with configurable attribute precedence.

## Features

- 🚀 Zero additional database queries for nested resource access
- 🔗 Multi-level nested resource context propagation
- ⚡ Configurable attribute precedence for handling conflicts
- 🎯 Laravel best practices and code structure
- 🧪 Comprehensive test coverage

## Installation

```bash
composer require contextify/laravel-resource-context
```

The package will auto-register the service provider.

## Usage

### Basic Usage

Consider this database structure: `Author` → `Series` → `Book`

```sql
-- Database structure
Author: [id, name]
Series: [id, name, author_id]
Book: [id, name, series_id]
```

Instead of extending `JsonResource`, extend `ContextualResource`:

```php
<?php

namespace App\Http\Resources;

use Contextify\LaravelResourceContext\ContextualResource;

class AuthorResource extends ContextualResource
{
    protected function transformResource($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'series' => $this->createSeriesCollection(),
        ];
    }

    private function createSeriesCollection()
    {
        // Create collection normally - context propagates automatically
        $series = SeriesResource::collection($this->whenLoaded('series'));

        // PRIORITY CONTEXT: Set author info as high-priority data
        // This ensures BookResource gets author name even if series also has 'name'
        foreach ($series as $seriesResource) {
            $seriesResource->withPriorityContext([
                'author_name' => $this->name,  // High-priority author name
                'author_id' => $this->id       // High-priority author ID
            ]);
        }

        return $series;
    }
}

class SeriesResource extends ContextualResource
{
    protected function transformResource($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Gets author_name from priority context set by AuthorResource
            'author_name' => $this->getParentAttribute('author_name'),
            'books' => BookResource::collection($this->whenLoaded('books')),
        ];
    }
}

class BookResource extends ContextualResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);

        // PRIORITY CONFIGURATION: Tell the system that 'author_name' should
        // always check priority context first, bypassing regular context
        $this->setPriorityAttributes(['author_name']);
    }

    protected function transformResource($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            // THE MAGIC: Because 'author_name' is configured as priority attribute,
            // this will get "J.K. Rowling" from priority context, NOT "Harry Potter" from series
            'author_name' => $this->getContextualAttribute('author_name'),

            // REGULAR CONTEXT: Gets "Harry Potter" from series context
            // Since 'name' is not a priority attribute, it follows normal precedence
            'series_name' => $this->getParentAttribute('name'),
        ];
    }
}
```

### Available Methods

#### Basic Context Access

```php
// PARENT CONTEXT: Get specific attribute from any parent in the chain
$userName = $this->getParentAttribute('name');           // "J.K. Rowling" (from Author)
$userEmail = $this->getParentAttribute('email', 'default@example.com');

// CHECK EXISTENCE: Verify if parent has attribute before using
if ($this->hasParentAttribute('name')) {
    // Safe to use - parent has this attribute
}

// GET ALL: Retrieve complete parent context as array
$allParentData = $this->getAllParentAttributes();        // ['name' => 'J.K. Rowling', 'id' => 1, ...]

// CONTEXTUAL: Smart fallback - checks priority → parent → resource
$email = $this->getContextualAttribute('email');         // Checks all levels

// RESOURCE ONLY: Force get from current resource, ignore all parent data
$localName = $this->getResourceAttribute('name');        // "Philosopher's Stone" (book name)

// HIGHER LEVEL ONLY: Force get from parent/priority, ignore resource
$parentName = $this->getHigherLevelAttribute('name');    // "J.K. Rowling" (never book name)
```

#### Attribute Precedence Control

When parent and resource have the same attribute name, you can control which takes priority:

```php
class ProductResource extends ContextualResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);

        // BATCH CONFIGURATION: Set multiple attributes to prioritize parent values
        $this->setPriorityAttributes(['name', 'category']);

        // INDIVIDUAL CONFIGURATION: Add one attribute at a time
        $this->usePriorityForAttribute('brand');
    }

    protected function transformResource($request)
    {
        return [
            'id' => $this->id,

            // REGULAR BEHAVIOR: Gets local product name (priority not configured)
            'name' => $this->name,

            // PRIORITY BEHAVIOR: Gets parent category (configured as priority)
            'category' => $this->getContextualAttribute('category'),

            // PRIORITY BEHAVIOR: Gets parent brand (configured as priority)
            'brand' => $this->getContextualAttribute('brand'),
        ];
    }
}
```

#### Priority Context

Set high-priority context that overrides regular parent context:

```php
// REGULAR CONTEXT: Normal parent data (medium priority)
$resource->withContext(['name' => 'Parent Name']);

// PRIORITY CONTEXT: High-importance data (highest priority)
$resource->withPriorityContext(['name' => 'Priority Name']);

// CONFIGURE: Tell system which attributes should check priority first
$resource->setPriorityAttributes(['name']);

// RESULT: getContextualAttribute('name') returns 'Priority Name'
// Because: priority context (highest) > regular context (medium) > resource (lowest)
```

### Manual Context Usage

```php
$postResource = new PostResource($post);

// STEP 1: Set regular context (medium priority)
$postResource->withContext([
    'user_id' => $user->id,
    'user_name' => $user->name,
]);

// STEP 2: Set priority context for critical data (highest priority)
$postResource->withPriorityContext([
    'status' => 'featured',    // Admin override
    'priority' => 'high'       // System priority
]);

// STEP 3: Configure which attributes should use priority-first lookup
$postResource->setPriorityAttributes(['status']);

// RESULT: getContextualAttribute('status') = 'featured' (from priority context)
// RESULT: getContextualAttribute('user_name') = user name (from regular context)
```

### Controller Usage

```php
class AuthorController extends Controller
{
    public function show(Author $author)
    {
        // CRITICAL: Load all nested relationships to avoid N+1 queries
        // Context propagation works with eager-loaded data only
        $author->load(['series.books']);

        return new AuthorResource($author);
    }
}
```

### Example Output

```json
{
    "id": 1,
    "name": "J.K. Rowling",
    "series": [
        {
            "id": 1,
            "name": "Harry Potter",
            "author_name": "J.K. Rowling",
            "books": [
                {
                    "id": 1,
                    "name": "Philosopher's Stone",
                    "author_name": "J.K. Rowling",
                    "series_name": "Harry Potter"
                },
                {
                    "id": 2,
                    "name": "Chamber of Secrets",
                    "author_name": "J.K. Rowling",
                    "series_name": "Harry Potter"
                }
            ]
        }
    ]
}
```

### The Name Conflict Problem

In this example, all three models have a `name` attribute:
- Author name: "J.K. Rowling"
- Series name: "Harry Potter"
- Book name: "Philosopher's Stone"

**Without priority context**: `getParentAttribute('name')` in BookResource would return "Harry Potter" (series name) instead of "J.K. Rowling" (author name).

**With priority context**: We set `author_name` as priority context, so BookResource can access the author name reliably while still getting series name through regular context.

## Key Benefits

- **Zero Additional Queries**: Child resources access parent data without extra database hits
- **Flexible Precedence**: Configure which attributes prioritize parent vs local values
- **Multi-level Context**: Access grandparent and deeper level attributes seamlessly
- **Memory Efficient**: Lightweight implementation with minimal memory overhead (~26MB for full test suite)
- **Laravel Compatible**: Works with all Laravel resource features (`when`, `whenLoaded`, etc.)

## Requirements

- PHP ^8.1
- Laravel ^10.0|^11.0

## Testing

```bash
composer test

# Or with memory limit (if needed)
php -d memory_limit=128M vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.