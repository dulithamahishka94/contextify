<?php

namespace Contextify\LaravelResourceContext\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Contextify\LaravelResourceContext\ResourceContextServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ResourceContextServiceProvider::class,
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