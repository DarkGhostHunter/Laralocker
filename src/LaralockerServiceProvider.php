<?php

namespace DarkGhostHunter\Laralocker;

use DarkGhostHunter\Laralocker\Listeners\CleansJobListener;
use DarkGhostHunter\Laralocker\Listeners\LocksJobListener;
use DarkGhostHunter\Laralocker\Listeners\ReleasesJobListener;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
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
        $this->registerListeners();
    }

    /**
     * Register the Job Events to use with the Locker
     *
     * @return void
     */
    protected function registerListeners()
    {
        $this->app->resolving('events', function ($dispatcher) {
            $dispatcher->listen(JobProcessing::class, LocksJobListener::class);
            $dispatcher->listen(JobProcessed::class, ReleasesJobListener::class);
            $dispatcher->listen([JobFailed::class, JobExceptionOccurred::class], CleansJobListener::class);
        });
    }

    /**
     * Registers the Locker binding into the Service Container
     *
     * @return void
     */
    protected function registerLockerBinding()
    {
        $this->app->bind(Locker::class, function ($app) {
            $config = $app['config'];
            return new Locker(
                $app['cache']->store($config['laralocker.cache']),
                $config['laralocker.prefix'],
                $config['laralocker.ttl'],
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
                __DIR__.'/../config/config.php' => config_path('laralocker.php'),
            ], 'config');
        }
    }
}
