<?php

namespace Contextify\LaravelResourceOptimizer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Contextify\LaravelResourceOptimizer\ResourceOptimizerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ResourceOptimizerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}