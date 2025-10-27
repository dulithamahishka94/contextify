<?php

namespace Contextify\LaravelResourceOptimizer\Tests;

use Illuminate\Http\Request;
use Contextify\LaravelResourceOptimizer\OptimizedResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OptimizedResourceTest extends TestCase
{
    public function testBasicResourceTransformation()
    {
        $resource = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item']);
        $result = $resource->toArray(new Request());

        $this->assertEquals([
            'id' => 1,
            'name' => 'Test Item',
            'resource_id' => $resource->getResourceId(),
        ], $result);
    }

    public function testPerformanceMonitoring()
    {
        $resource = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item']);
        $resource->toArray(new Request());

        $metrics = $resource->getPerformanceMetrics();

        $this->assertArrayHasKey('execution_time', $metrics);
        $this->assertArrayHasKey('memory_used', $metrics);
        $this->assertArrayHasKey('resource_class', $metrics);
        $this->assertEquals(TestOptimizedResource::class, $metrics['resource_class']);
    }

    public function testCaching()
    {
        Cache::flush();

        $resource = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item']);

        // First call should miss cache
        $result1 = $resource->toArray(new Request());

        // Second call with same data should hit cache
        $resource2 = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item']);
        $result2 = $resource2->toArray(new Request());

        $this->assertEquals($result1, $result2);
    }

    public function testDisablingOptimizations()
    {
        $resource = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item']);

        $resourceWithoutCaching = $resource->withoutCaching();
        $resourceWithoutMonitoring = $resource->withoutMonitoring();
        $resourceWithoutQueryDetection = $resource->withoutQueryDetection();

        // Should still work but with optimizations disabled
        $result = $resourceWithoutCaching
            ->withoutMonitoring()
            ->withoutQueryDetection()
            ->toArray(new Request());

        $this->assertIsArray($result);
    }

    public function testCustomCacheConfiguration()
    {
        $resource = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item']);

        $resource->withCacheTtl(7200)
                 ->withCacheTags(['test', 'optimization']);

        $result = $resource->toArray(new Request());

        $this->assertIsArray($result);
    }

    public function testQueryDetection()
    {
        // This test would require actual database queries to be meaningful
        // For now, just test that the feature doesn't break anything
        DB::enableQueryLog();

        $resource = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item']);
        $result = $resource->toArray(new Request());

        $this->assertIsArray($result);

        DB::disableQueryLog();
    }

    public function testGlobalPerformanceStats()
    {
        // Clear any existing stats
        TestOptimizedResource::resetGlobalPerformanceStats();

        // Create multiple resources to generate stats
        for ($i = 1; $i <= 3; $i++) {
            $resource = new TestOptimizedResource(['id' => $i, 'name' => "Test Item {$i}"]);
            $resource->toArray(new Request());
        }

        $stats = TestOptimizedResource::getGlobalPerformanceStats();

        $this->assertArrayHasKey(TestOptimizedResource::class, $stats);
        $classStats = $stats[TestOptimizedResource::class];

        $this->assertEquals(3, $classStats['count']);
        $this->assertArrayHasKey('avg_time', $classStats);
        $this->assertArrayHasKey('avg_memory', $classStats);
    }

    public function testCompositionHelpers()
    {
        $resource = new TestOptimizedResource(['id' => 1, 'name' => 'Test Item', 'active' => true]);

        $result = $resource->toArray(new Request());

        // Test that composition helpers are available
        $this->assertTrue(method_exists($resource, 'whenOptimized'));
        $this->assertTrue(method_exists($resource, 'whenLoadedOptimized'));
        $this->assertTrue(method_exists($resource, 'optimizedCollection'));
    }
}

class TestOptimizedResource extends OptimizedResource
{
    protected function transformResource($request)
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'resource_id' => $this->getResourceId(),
        ];
    }
}

class TestSlowResource extends OptimizedResource
{
    protected function transformResource($request)
    {
        // Simulate slow transformation
        usleep(150000); // 150ms

        return [
            'id' => $this->resource['id'],
            'processed_at' => now()->toISOString(),
        ];
    }
}

class TestMemoryIntensiveResource extends OptimizedResource
{
    protected function transformResource($request)
    {
        // Simulate memory-intensive operation
        $largeArray = array_fill(0, 100000, 'memory test');

        return [
            'id' => $this->resource['id'],
            'data_size' => count($largeArray),
        ];
    }
}