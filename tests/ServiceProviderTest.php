<?php

namespace DarkGhostHunter\Laralocker\Tests;

use DarkGhostHunter\Laralocker\LaralockerServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
    use RegistersPackage;

    public function testRegistersPackage()
    {
        $this->assertArrayHasKey(LaralockerServiceProvider::class, $this->app->getLoadedProviders());
    }

    public function testRegisterServices()
    {
        $this->assertTrue($this->app->has('DarkGhostHunter\Laralocker\LockerManager'));
    }

    public function testHasConfig()
    {
        $this->assertEquals(require __DIR__ . '/../config/laralocker.php', config('laralocker'));
    }

    public function testPublishesConfig()
    {
        $file = $this->app->configPath('laralocker.php');

        if (file_exists($file)) {
            unlink($file);
        }

        Artisan::call('vendor:publish', [
            '--provider' => LaralockerServiceProvider::class
        ]);

        $this->assertFileEquals(__DIR__ . '/../config/laralocker.php', $file);

        unlink($file);
    }
}
