# Laravel Resource Context

A Laravel package that allows nested resources to access parent resource attributes without additional database queries.

## Features

- 🚀 Zero additional database queries for nested resource access
- 🔗 Multi-level nested resource context propagation
- 🎯 Laravel best practices and code structure
- 🧪 Comprehensive test coverage
- 📦 Easy composer installation

## Installation

```bash
composer require contextify/laravel-resource-context
```

The package will auto-register the service provider.

Optionally, publish the configuration:

```bash
php artisan vendor:publish --provider="Contextify\LaravelResourceContext\ResourceContextServiceProvider" --tag="config"
```

## Usage

### Basic Usage

Instead of extending `JsonResource`, extend `ContextualResource`:

```php
<?php

namespace App\Http\Resources;

use Contextify\LaravelResourceContext\ContextualResource;

class UserResource extends ContextualResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'posts' => PostResource::collection($this->whenLoaded('posts')),
        ];
    }
}

class PostResource extends ContextualResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            // Access parent user attributes without additional queries
            'author_name' => $this->getParentAttribute('name'),
            'author_email' => $this->getContextualAttribute('email'), // Fallback to current resource if not in parent
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}

class CommentResource extends ContextualResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            // Access grandparent user attributes
            'post_author_name' => $this->getParentAttribute('name'),
            'post_title' => $this->getParentAttribute('title'),
        ];
    }
}
```

### Available Methods

#### `getParentAttribute($key, $default = null)`
Get a specific attribute from any parent resource in the context chain.

```php
$userName = $this->getParentAttribute('name');
$userEmail = $this->getParentAttribute('email', 'no-email@example.com');
```

#### `hasParentAttribute($key)`
Check if a parent attribute exists.

```php
if ($this->hasParentAttribute('name')) {
    // Do something with the parent name
}
```

#### `getAllParentAttributes()`
Get all parent attributes as an array.

```php
$allParentData = $this->getAllParentAttributes();
```

#### `getContextualAttribute($key, $default = null)`
Get an attribute with fallback logic: checks parent context first, then current resource.

```php
// Will check parent context first, then current resource
$email = $this->getContextualAttribute('email');
```

### Manual Context Setting

You can also manually set context data:

```php
$postResource = new PostResource($post);
$postResource->withContext([
    'user_id' => $user->id,
    'user_name' => $user->name,
    'custom_data' => 'some value'
]);
```

### Example Controller Usage

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;

class UserController extends Controller
{
    public function show(User $user)
    {
        // Load relationships to avoid N+1 queries
        $user->load(['posts.comments']);

        return new UserResource($user);
    }
}
```

### Example Output

```json
{
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "posts": [
            {
                "id": 1,
                "title": "My First Post",
                "content": "Post content here...",
                "author_name": "John Doe",
                "author_email": "john@example.com",
                "comments": [
                    {
                        "id": 1,
                        "content": "Great post!",
                        "post_author_name": "John Doe",
                        "post_title": "My First Post"
                    }
                ]
            }
        ]
    }
}
```

## How It Works

1. **Context Stack**: The package maintains a context stack that accumulates parent resource attributes as it traverses nested resources.

2. **Automatic Propagation**: When a resource is transformed, it automatically pushes its attributes to the context stack and propagates this context to child resources.

3. **Zero Queries**: Child resources can access parent attributes directly from the context without triggering additional database queries.

4. **Laravel Integration**: Seamlessly works with Laravel's existing resource methods like `when()`, `whenLoaded()`, and `mergeWhen()`.

## Configuration

The package includes a configuration file with the following options:

```php
return [
    // Automatically propagate context to nested resources
    'auto_propagate' => true,

    // Maximum context stack depth (prevents infinite recursion)
    'context_limit' => 100,

    // Enable debug mode for development
    'debug' => env('RESOURCE_CONTEXT_DEBUG', false),
];
```

## Requirements

- PHP ^8.1
- Laravel ^10.0|^11.0

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.