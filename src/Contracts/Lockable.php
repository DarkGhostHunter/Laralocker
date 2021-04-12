<?php

namespace DarkGhostHunter\Laralocker\Contracts;

interface Lockable
{
    /**
     * Returns the Slot being used by the Job.
     *
     * @return mixed
     */
    public function getSlot();

    /**
     * Saves the Slot to use by the Job.
     *
     * @param $slot
     * @return void
     */
    public function setSlot($slot): void;

    /**
     * Return the starting slot for the Jobs.
     *
     * @return mixed
     */
    public function startFrom();

    /**
     * The next slot to check for availability.
     *
     * @param mixed $slot
     * @return mixed
     */
    public function next($slot);

    /**
     * Reserves the current Job slot.
     *
     * @return mixed
     */
    public function reserveSlot();

    /**
     * Releases the current Job slot and updates the last slot.
     *
     * @return void
     */
    public function releaseSlot(): void;

    /**
     * Clears the current Job slot reserved.
     *
     * @return void
     */
    public function clearSlot(): void;
}
