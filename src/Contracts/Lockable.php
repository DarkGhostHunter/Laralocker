<?php

namespace DarkGhostHunter\Laralocker\Contracts;

interface Lockable
{
    /**
     * Return the starting slot for the Jobs
     *
     * @return mixed
     */
    public function startFrom();

    /**
     * The next slot to check for availability
     *
     * @param mixed $slot
     * @return mixed
     */
    public function next($slot);

    /**
     * Locks the current Job
     *
     * @return mixed
     */
    public function lock();

    /**
     * Unlocks the current Job
     *
     * @return void
     */
    public function release();

    /**
     * Clears the Job
     *
     * @return void
     */
    public function clear();
}
