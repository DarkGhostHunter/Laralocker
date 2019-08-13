<?php

namespace DarkGhostHunter\Laralocker\Tests\Stubs;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use DarkGhostHunter\Laralocker\HandlesSlot;

class LockableConcurrentJob implements Lockable
{
    use HandlesSlot;

    public static $slots = [];

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
