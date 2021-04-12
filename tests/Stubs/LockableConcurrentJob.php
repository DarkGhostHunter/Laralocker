<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesSlot;
use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;

class LockableConcurrentJob implements Lockable, ShouldQueue
{
    use HandlesSlot;

    public static $slots = [];

    public $currentSlot;

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
        self::$slots[] = $this->reserveSlot();
    }

    public function startFrom()
    {
        return 0;
    }

    public function next($slot)
    {
        return $slot + 10;
    }
}
