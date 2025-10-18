<?php

namespace Contextify\LaravelResourceContext\Tests;

use Illuminate\Http\Request;
use Contextify\LaravelResourceContext\ContextualResource;

class ContextualResourceTest extends TestCase
{
    public function testBasicResourceContext()
    {
        $parentResource = new TestParentResource(['id' => 1, 'name' => 'John Doe']);
        $result = $parentResource->toArray(new Request());

        $this->assertEquals([
            'id' => 1,
            'name' => 'John Doe',
            'child' => [
                'id' => 2,
                'title' => 'Child Item',
                'parent_name' => 'John Doe',
                'nested' => [
                    'id' => 3,
                    'description' => 'Nested Item',
                    'grandparent_name' => 'John Doe',
                ],
            ],
        ], $result);
    }

    public function testNestedResourceContext()
    {
        $parentResource = new TestParentResource(['id' => 1, 'name' => 'John Doe']);
        $result = $parentResource->toArray(new Request());

        $this->assertEquals('John Doe', $result['child']['nested']['grandparent_name']);
    }

    public function testGetParentAttribute()
    {
        $resource = new TestChildResource(['id' => 2, 'title' => 'Child']);
        $resource->withContext(['name' => 'Parent Name', 'email' => 'parent@example.com']);

        $this->assertEquals('Parent Name', $resource->getParentAttribute('name'));
        $this->assertEquals('parent@example.com', $resource->getParentAttribute('email'));
        $this->assertNull($resource->getParentAttribute('nonexistent'));
        $this->assertEquals('default', $resource->getParentAttribute('nonexistent', 'default'));
    }

    public function testHasParentAttribute()
    {
        $resource = new TestChildResource(['id' => 2, 'title' => 'Child']);
        $resource->withContext(['name' => 'Parent Name']);

        $this->assertTrue($resource->hasParentAttribute('name'));
        $this->assertFalse($resource->hasParentAttribute('nonexistent'));
    }

    public function testGetAllParentAttributes()
    {
        $resource = new TestChildResource(['id' => 2, 'title' => 'Child']);
        $context = ['name' => 'Parent Name', 'email' => 'parent@example.com'];
        $resource->withContext($context);

        $this->assertEquals($context, $resource->getAllParentAttributes());
    }

    public function testGetContextualAttribute()
    {
        $resource = new TestChildResource(['id' => 2, 'title' => 'Child Title']);
        $resource->withContext(['name' => 'Parent Name']);

        $this->assertEquals('Parent Name', $resource->getContextualAttribute('name'));
        $this->assertEquals('Child Title', $resource->getContextualAttribute('title'));
        $this->assertEquals('default', $resource->getContextualAttribute('nonexistent', 'default'));
    }

    public function testWithContext()
    {
        $resource = new TestChildResource(['id' => 2, 'title' => 'Child']);
        $context1 = ['name' => 'Parent Name'];
        $context2 = ['email' => 'parent@example.com'];

        $resource->withContext($context1)->withContext($context2);

        $this->assertEquals('Parent Name', $resource->getParentAttribute('name'));
        $this->assertEquals('parent@example.com', $resource->getParentAttribute('email'));
    }

    public function testPriorityAttributes()
    {
        $resource = new TestConflictResource(['id' => 1, 'name' => 'Child Name']);
        $resource->withContext(['name' => 'Parent Name']);

        $this->assertEquals('Child Name', $resource->getContextualAttribute('name'));

        $resource->setPriorityAttributes(['name']);
        $this->assertEquals('Parent Name', $resource->getContextualAttribute('name'));
    }

    public function testUsePriorityForAttribute()
    {
        $resource = new TestConflictResource(['id' => 1, 'name' => 'Child Name']);
        $resource->withContext(['name' => 'Parent Name']);

        $this->assertEquals('Child Name', $resource->getContextualAttribute('name'));

        $resource->usePriorityForAttribute('name');
        $this->assertEquals('Parent Name', $resource->getContextualAttribute('name'));
    }

    public function testGetHigherLevelAttribute()
    {
        $resource = new TestConflictResource(['id' => 1, 'name' => 'Child Name']);
        $resource->withContext(['name' => 'Parent Name', 'other' => 'Parent Other']);
        $resource->withPriorityContext(['name' => 'Priority Name']);

        $this->assertEquals('Priority Name', $resource->getHigherLevelAttribute('name'));
        $this->assertEquals('Parent Other', $resource->getHigherLevelAttribute('other'));
        $this->assertNull($resource->getHigherLevelAttribute('nonexistent'));
    }

    public function testGetResourceAttribute()
    {
        $resource = new TestConflictResource(['id' => 1, 'name' => 'Child Name']);
        $resource->withContext(['name' => 'Parent Name']);

        $this->assertEquals('Child Name', $resource->getResourceAttribute('name'));
        $this->assertEquals(1, $resource->getResourceAttribute('id'));
        $this->assertNull($resource->getResourceAttribute('nonexistent'));
    }

    public function testWithPriorityContext()
    {
        $resource = new TestConflictResource(['id' => 1, 'name' => 'Child Name']);
        $resource->withContext(['name' => 'Parent Name']);
        $resource->withPriorityContext(['name' => 'Priority Name', 'type' => 'priority']);

        $resource->setPriorityAttributes(['name']);
        $this->assertEquals('Priority Name', $resource->getContextualAttribute('name'));
        $this->assertEquals(1, $resource->getContextualAttribute('id'));
    }
}

class TestParentResource extends ContextualResource
{
    protected function transformResource($request = null)
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'child' => new TestChildResource(['id' => 2, 'title' => 'Child Item']),
        ];
    }
}

class TestChildResource extends ContextualResource
{
    protected function transformResource($request = null)
    {
        return [
            'id' => $this->resource['id'],
            'title' => $this->resource['title'],
            'parent_name' => $this->getParentAttribute('name'),
            'nested' => new TestNestedResource(['id' => 3, 'description' => 'Nested Item']),
        ];
    }
}

class TestNestedResource extends ContextualResource
{
    protected function transformResource($request = null)
    {
        return [
            'id' => $this->resource['id'],
            'description' => $this->resource['description'],
            'grandparent_name' => $this->getParentAttribute('name'),
        ];
    }
}

class TestConflictResource extends ContextualResource
{
    protected function transformResource($request = null)
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'contextual_name' => $this->getContextualAttribute('name'),
        ];
    }
}