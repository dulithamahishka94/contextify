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
        $testClass->pushContext();

        $testClass->withContext(['level' => 2, 'name' => 'Level 2']);
        $testClass->pushContext();

        $current = $testClass->getCurrentContext();
        $this->assertArrayHasKey('level', $current);
    }

    public function testContextPropagation()
    {
        $testClass = new TestResourceContextClass();
        $testClass->resource = ['id' => 1, 'name' => 'Resource Name'];

        $mockResource = $this->createMock(\Illuminate\Http\Resources\Json\JsonResource::class);
        $mockResource->expects($this->once())
                   ->method('withContext')
                   ->willReturnSelf();

        $result = $testClass->propagateContextToResource($mockResource);
        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class, $result);
    }
}

class TestResourceContextClass
{
    use ResourceContext;

    public $resource;

    public function pushContext(): void
    {
        $this->pushContext();
    }

    public function getCurrentContext(): array
    {
        return $this->getCurrentContext();
    }

    public function propagateContextToResource($resource)
    {
        return $this->propagateContextToResource($resource);
    }
}