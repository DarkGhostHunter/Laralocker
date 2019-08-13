<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesSlot;
use Illuminate\Contracts\Queue\ShouldQueue;

class LockableJob implements ShouldQueue, Lockable
{
    use HandlesSlot;

    public static $current_slot = 0;

    public function handle()
    {
        $this->reserveSlot();

        static::$current_slot = $this->slot;

        $this->releaseSlot();
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
