<?php

namespace Contextify\LaravelResourceOptimizer\Tests;

use Contextify\LaravelResourceOptimizer\Services\PerformanceMonitor;
use Contextify\LaravelResourceOptimizer\Services\QueryDetector;
use Contextify\LaravelResourceOptimizer\Services\ResourceCache;

class ResourceOptimizerServiceProviderTest extends TestCase
{
    public function testConfigurationIsLoaded()
    {
        $this->assertNotNull(config('resource-optimizer'));
        $this->assertTrue(config('resource-optimizer.performance_monitoring'));
        $this->assertIsArray(config('resource-optimizer.performance'));
        $this->assertIsArray(config('resource-optimizer.query_detection'));
        $this->assertIsArray(config('resource-optimizer.caching'));
    }

    public function testConfigurationDefaults()
    {
        $config = config('resource-optimizer');

        // Test performance monitoring defaults
        $this->assertTrue($config['performance_monitoring']);
        $this->assertEquals(0.1, $config['performance']['slow_threshold']);

        // Test query detection defaults
        $this->assertTrue($config['query_detection']['enabled']);
        $this->assertEquals(5, $config['query_detection']['threshold']);

        // Test caching defaults
        $this->assertTrue($config['caching']['enabled']);
        $this->assertEquals(3600, $config['caching']['default_ttl']);
    }

    public function testServicesAreRegistered()
    {
        // Test that optimization services are registered
        $this->assertTrue($this->app->bound('resource-optimizer.monitor'));
        $this->assertTrue($this->app->bound('resource-optimizer.detector'));
        $this->assertTrue($this->app->bound('resource-optimizer.cache'));

        // Test that services can be resolved
        $monitor = $this->app->make('resource-optimizer.monitor');
        $detector = $this->app->make('resource-optimizer.detector');
        $cache = $this->app->make('resource-optimizer.cache');

        $this->assertInstanceOf(PerformanceMonitor::class, $monitor);
        $this->assertInstanceOf(QueryDetector::class, $detector);
        $this->assertInstanceOf(ResourceCache::class, $cache);
    }

    public function testServicesAreSingletons()
    {
        $monitor1 = $this->app->make('resource-optimizer.monitor');
        $monitor2 = $this->app->make('resource-optimizer.monitor');

        $detector1 = $this->app->make('resource-optimizer.detector');
        $detector2 = $this->app->make('resource-optimizer.detector');

        $cache1 = $this->app->make('resource-optimizer.cache');
        $cache2 = $this->app->make('resource-optimizer.cache');

        // Test that same instances are returned (singleton behavior)
        $this->assertSame($monitor1, $monitor2);
        $this->assertSame($detector1, $detector2);
        $this->assertSame($cache1, $cache2);
    }

    public function testPerformanceMonitorService()
    {
        $monitor = $this->app->make('resource-optimizer.monitor');

        $testMetrics = [
            'execution_time' => 0.05,
            'memory_used' => 1024,
            'resource_id' => 'test_123',
        ];

        $monitor->recordMetrics('TestResource', $testMetrics);

        $metrics = $monitor->getMetrics('TestResource');
        $this->assertCount(1, $metrics);
        $this->assertEquals($testMetrics, $metrics[0]);

        $allMetrics = $monitor->getAllMetrics();
        $this->assertArrayHasKey('TestResource', $allMetrics);

        $monitor->clearMetrics();
        $this->assertEmpty($monitor->getAllMetrics());
    }

    public function testQueryDetectorService()
    {
        $detector = $this->app->make('resource-optimizer.detector');

        $testResults = [
            'query_count' => 10,
            'potential_n_plus_one' => true,
            'suggestions' => ['eager load relationships'],
        ];

        $detector->recordDetection('TestResource', $testResults);

        $results = $detector->getResults('TestResource');
        $this->assertCount(1, $results);
        $this->assertEquals($testResults, $results[0]);

        $allResults = $detector->getAllResults();
        $this->assertArrayHasKey('TestResource', $allResults);

        $detector->clearResults();
        $this->assertEmpty($detector->getAllResults());
    }

    public function testResourceCacheService()
    {
        $cache = $this->app->make('resource-optimizer.cache');

        // Test cache miss
        $result = $cache->get('test_key');
        $this->assertNull($result);

        // Test cache store
        $success = $cache->put('test_key', 'test_value', 3600);
        $this->assertTrue($success);

        // Test cache hit
        $result = $cache->get('test_key');
        $this->assertEquals('test_value', $result);

        // Test statistics
        $stats = $cache->getStats();
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('stores', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);

        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(1, $stats['stores']);
        $this->assertEquals(50.0, $stats['hit_rate']); // 1 hit out of 2 total requests
    }
}