<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesSlot;
use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mockery;

class LockableCustomJob implements Lockable, ShouldQueue
{
    use HandlesSlot;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [new LockerJobMiddleware()];
    }

    public int $slotTtl = 99;

    public string $prefix = 'test_prefix';

    public static bool $cacheCalled = false;

    public function handle()
    {
//        $this->reserveSlot();
    }

    public function cache()
    {
        static::$cacheCalled = true;

        return app('cache.store');
    }

    public function startFrom()
    {
        return 1;
    }

    public function next($slot)
    {
        return $slot + 10;
    }
}
