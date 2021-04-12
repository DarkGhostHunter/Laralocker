<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesSlot;
use DarkGhostHunter\Laralocker\LockerJobMiddleware;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LockableExceptionJob implements ShouldQueue, Lockable
{
    use InteractsWithQueue;
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

    public static $current_slot = 0;

    public function handle()
    {
        static::$current_slot = $this->slot;

        throw new Exception('error');
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
