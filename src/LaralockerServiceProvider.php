<?php

namespace DarkGhostHunter\Laralocker;

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
        $this->mergeConfigFrom(__DIR__ . '/../config/laralocker.php', 'laralocker');

        $this->registerLockerBinding();
    }

    /**
     * Registers the Locker binding into the Service Container
     *
     * @return void
     */
    protected function registerLockerBinding()
    {
        $this->app->singleton(LockerManager::class, function ($app) {
            $config = $app['config'];
            return new LockerManager(
                $app['cache']->store($config['laralocker.cache']),
                $config['laralocker.prefix'],
                $config['laralocker.ttl']
            );
        });
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
                __DIR__ . '/../config/laralocker.php' => config_path('laralocker.php'),
            ], 'config');
        }
    }
}
