<?php

namespace Contextify\LaravelResourceContext\Tests;

class ServiceProviderTest extends TestCase
{
    public function testServiceProviderRegistration()
    {
        $this->assertArrayHasKey(
            'Contextify\LaravelResourceContext\ResourceContextServiceProvider',
            $this->app->getLoadedProviders()
        );
    }

    public function testConfigurationMerged()
    {
        $this->assertTrue(config('resource-context.auto_propagate'));
        $this->assertEquals(100, config('resource-context.context_limit'));
        $this->assertFalse(config('resource-context.debug'));
    }
}