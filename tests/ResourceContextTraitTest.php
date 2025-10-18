<?php

namespace Contextify\LaravelResourceContext\Tests;

use Contextify\LaravelResourceContext\Traits\ResourceContext;

class ResourceContextTraitTest extends TestCase
{
    public function testTraitBasicFunctionality()
    {
        $testClass = new TestResourceContextClass();

        $testClass->withContext(['name' => 'Test Name', 'email' => 'test@example.com']);

        $this->assertEquals('Test Name', $testClass->getParentAttribute('name'));
        $this->assertEquals('test@example.com', $testClass->getParentAttribute('email'));
        $this->assertTrue($testClass->hasParentAttribute('name'));
        $this->assertFalse($testClass->hasParentAttribute('nonexistent'));
    }

    public function testContextStacking()
    {
        $testClass = new TestResourceContextClass();

        $testClass->withContext(['level' => 1, 'name' => 'Level 1']);
        $testClass->testPushContext();

        $testClass->withContext(['level' => 2, 'name' => 'Level 2']);
        $testClass->testPushContext();

        $current = $testClass->testGetCurrentContext();
        $this->assertArrayHasKey('level', $current);
    }

    public function testContextPropagation()
    {
        $testClass = new TestResourceContextClass();
        $testClass->resource = ['id' => 1, 'name' => 'Resource Name'];

        $mockResource = $this->createMock(\Illuminate\Http\Resources\Json\JsonResource::class);
        $mockResource->expects($this->once())
                   ->method('toArray')
                   ->with($this->anything())
                   ->willReturn(['mocked' => 'data']);

        $result = $testClass->testPropagateContextToResource($mockResource);
        $this->assertEquals(['mocked' => 'data'], $result);
    }

    public function testPriorityContext()
    {
        $testClass = new TestResourceContextClass();

        $testClass->withContext(['name' => 'Regular Name']);
        $testClass->withPriorityContext(['name' => 'Priority Name']);

        $this->assertEquals('Priority Name', $testClass->getHigherLevelAttribute('name'));
        $this->assertEquals('Regular Name', $testClass->getParentAttribute('name'));
    }

    public function testGetResourceAttribute()
    {
        $testClass = new TestResourceContextClass();
        $testClass->resource = ['id' => 1, 'name' => 'Resource Name'];

        $testClass->withContext(['name' => 'Parent Name']);

        $this->assertEquals('Resource Name', $testClass->getResourceAttribute('name'));
        $this->assertEquals(1, $testClass->getResourceAttribute('id'));
        $this->assertNull($testClass->getResourceAttribute('nonexistent'));
    }

    public function testGetHigherLevelAttribute()
    {
        $testClass = new TestResourceContextClass();
        $testClass->resource = ['id' => 1, 'name' => 'Resource Name'];

        $testClass->withContext(['name' => 'Parent Name']);
        $testClass->withPriorityContext(['name' => 'Priority Name']);

        $this->assertEquals('Priority Name', $testClass->getHigherLevelAttribute('name'));
        $this->assertNull($testClass->getHigherLevelAttribute('nonexistent'));
    }
}

class TestResourceContextClass
{
    use ResourceContext;

    public $resource;

    public function testPushContext(): void
    {
        $this->pushContext();
    }

    public function testGetCurrentContext(): array
    {
        return $this->getCurrentContext();
    }

    public function testPropagateContextToResource($resource)
    {
        return $this->propagateContextToResource($resource);
    }
}