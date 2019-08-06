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
}
