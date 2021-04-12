<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesLockerSlot;
use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;

class LockableCustomJob implements Lockable, ShouldQueue
{
    use HandlesLockerSlot;

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
