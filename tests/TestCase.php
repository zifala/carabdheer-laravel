<?php

declare(strict_types=1);

namespace Zifala\Carabdheer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Zifala\Carabdheer\CarabdheerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CarabdheerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('carabdheer.key', 'crb_testkey0000000000000000000000000000000');
        $app['config']->set('carabdheer.url', 'https://carabdheer.test/api/v1/ingest');
    }
}
