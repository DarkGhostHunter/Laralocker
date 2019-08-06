<?php

namespace DarkGhostHunter\Laralocker;

trait Locks
{
    /**
     * Slot being used by the Job
     *
     * @var mixed
     */
    protected $slot;

    /**
     * Locks the current Job
     *
     * @return mixed
     */
    public function lock()
    {
        app(LockerManager::class)->lockJob($this);

        return $this->slot;
    }

    /**
     * Unlocks the current Job
     *
     * @return void
     */
    public function release()
    {
        app(LockerManager::class)->releaseJob($this);
    }

    /**
     * Clears the Job
     *
     * @return void
     */
    public function clear()
    {
        app(LockerManager::class)->clearJobFailed($this);
    }
}
