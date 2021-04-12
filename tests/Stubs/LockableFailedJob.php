<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesLockerSlot;
use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LockableFailedJob implements ShouldQueue, Lockable
{
    use InteractsWithQueue;
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

    public static $current_slot = 0;

    public function handle()
    {
//        $this->reserveSlot();

        static::$current_slot = $this->slot;

//        $this->clearSlot();
        $this->fail();
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
