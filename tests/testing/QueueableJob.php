<?php

namespace DarkGhostHunter\Laralocker\Tests\testing;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueueableJob implements ShouldQueue, Lockable
{
    public static $current_slot = 0;

    public $slot;

    public function handle()
    {
        static::$current_slot = $this->slot;
    }

    /**
     * Return the starting slot for the Jobs
     *
     * @return mixed
     */
    public function startFrom()
    {
        return 1;
    }

    /**
     * The next slot to check for availability
     *
     * @param mixed $slot
     * @return mixed
     */
    public function next($slot)
    {
        return $slot + 10;
    }

}
