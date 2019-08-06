<?php

namespace DarkGhostHunter\Laralocker\Listeners;

use DarkGhostHunter\Laralocker\Locker;

abstract class AbstractJobListener
{
    /**
     * The Locker manager instance
     *
     * @var \DarkGhostHunter\Laralocker\Locker
     */
    protected $locker;

    /**
     * Create the event listener.
     *
     * @param \DarkGhostHunter\Laralocker\Locker $locker
     */
    public function __construct(Locker $locker)
    {
        $this->locker = $locker;
    }

    /**
     * Sets the Cache for the Locker instance if needed
     *
     * @param $instance
     */
    protected function prepareCache($instance)
    {
        if (method_exists($instance, 'cache')) {
            $this->locker->setRepository($instance->cache());
        }
    }
}
