<?php

namespace DarkGhostHunter\Laralocker;

trait HandlesSlot
{
    /**
     * Slot being used by the Job
     *
     * @var mixed
     */
    protected $slot;

    /**
     * Returns the Slot being used by the Job
     *
     * @return mixed
     */
    public function getSlot()
    {
        return $this->slot;
    }

    /**
     * Saves the Slot to use by the Job
     *
     * @param $slot
     * @return void
     */
    public function setSlot($slot)
    {
        $this->slot = $slot;
    }

    /**
     * Reserves the current Job slot
     *
     * @return mixed
     */
    public function reserveSlot()
    {
        app(LockerManager::class)->lockSlot($this);

        return $this->slot;
    }

    /**
     * Releases the current Job slot and updates the last slot
     *
     * @return void
     */
    public function releaseSlot()
    {
        app(LockerManager::class)->releaseSlot($this);
    }

    /**
     * Clears the current Job slot reserved
     *
     * @return void
     */
    public function clearSlot()
    {
        app(LockerManager::class)->clearSlot($this);
    }
}
