<?php

namespace DarkGhostHunter\Laralocker\Tests;

use DarkGhostHunter\Laralocker\LaralockerServiceProvider;
use PHPUnit\Framework\TestCase;

class ServiceProviderTest extends TestCase
{
    public function testRegistersPackage()
    {
        $this->assertArrayHasKey(LaralockerServiceProvider::class, $this->app->getLoadedProviders());
    }
}
