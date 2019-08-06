<?php

namespace DarkGhostHunter\Laralocker;

use DarkGhostHunter\Laralocker\Listeners\CleansJobListener;
use DarkGhostHunter\Laralocker\Listeners\LocksJobListener;
use DarkGhostHunter\Laralocker\Listeners\ReleasesJobListener;
use Illuminate\Support\ServiceProvider;

class LaralockerServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'laralocker');

        $this->registerLockerBinding();
    }

    /**
     * Registers the Locker binding into the Service Container
     *
     * @return void
     */
    protected function registerLockerBinding()
    {
        $this->app->singleton(LockerManager::class);
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('laralocker.php'),
            ], 'config');
        }
    }
}
