<?php

namespace DarkGhostHunter\Laralocker;

use DarkGhostHunter\Laralocker\Contracts\Lockable;
use Illuminate\Contracts\Cache\Repository;

class LockerManager
{
    /**
     * The Cache to use with the Locks
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected Repository $store;

    /**
     * Default prefix to add to the reservations
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Default time to live for the reservations
     *
     * @var int
     */
    protected int $ttl;

    /**
     * Create a new Locker Manager instance.
     *
     * @param \Illuminate\Contracts\Cache\Repository $store
     * @param string $prefix
     * @param int $ttl
     * @return void
     */
    public function __construct(Repository $store, string $prefix, int $ttl)
    {
        $this->store = $store;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    /**
     * Instances a new Locker object depending on the Job configuration.
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $job
     *
     * @return \DarkGhostHunter\Laralocker\Locker
     */
    protected function instanceLocker(Lockable $job): Locker
    {
        // While this Locker Manager class handles the Locker instance, the latter is what
        // does the magic of looking ahead, reserving, releasing and saving slots. This
        // way we avoid any shared instances of the Locker class to handle Job slots.
        return new Locker(
            $job,
            $this->useStore($job),
            $this->usePrefix($job),
            $this->useReservationTtl($job)
        );
    }

    /**
     * Locks the Job slot.
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $job
     *
     * @return void
     */
    public function lockSlot(Lockable $job): void
    {
        $this->instanceLocker($job)->reserveNextAvailableSlot();
    }

    /**
     * Releases the Job slot from for locking system and updates the last slot.
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $job
     *
     * @return void
     */
    public function releaseSlot(Lockable $job): void
    {
        $this->instanceLocker($job)->handleSlotRelease();
    }

    /**
     * Clears the Job reserved slot.
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $job
     *
     * @return void
     */
    public function clearSlot(Lockable $job): void
    {
        $this->instanceLocker($job)->releaseSlot();
    }

    /**
     * Returns the Cache to use with the Job.
     *
     * @param  \DarkGhostHunter\Laralocker\Contracts\Lockable  $job
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function useStore(Lockable $job): Repository
    {
        return method_exists($job, 'cache') ? $job->cache() : $this->store;
    }

    /**
     * Return the Job maximum time to live before timing out.
     *
     * @param  \DarkGhostHunter\Laralocker\Contracts\Lockable  $job
     *
     * @return int
     */
    protected function useReservationTtl(Lockable $job): int
    {
        return $job->slotTtl
            ?? $job->timeout
            ?? (method_exists($job, 'retryUntil') ? $job->retryUntil() : $this->ttl);
    }

    /**
     * Return the prefix to use with the Locker.
     *
     * @param  \DarkGhostHunter\Laralocker\Contracts\Lockable  $job
     *
     * @return string
     */
    protected function usePrefix(Lockable $job): string
    {
        return $job->prefix ?? $this->prefix;
    }
}
