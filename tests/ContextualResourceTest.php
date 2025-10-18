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
}

class TestParentResource extends ContextualResource
{
    public function toArray($request = null)
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
    public function toArray($request = null)
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
    public function toArray($request = null)
    {
        return [
            'id' => $this->resource['id'],
            'description' => $this->resource['description'],
            'grandparent_name' => $this->getParentAttribute('name'),
        ];
    }
}