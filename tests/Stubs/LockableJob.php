<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesLockerSlot;
use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;

class LockableJob implements ShouldQueue, Lockable
{
    use HandlesLockerSlot;

    public static $current_slot = 0;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [new LockerJobMiddleware()];
    }

    public function handle()
    {
//        $this->reserveSlot();

        static::$current_slot = $this->slot;

//        $this->releaseSlot();
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
