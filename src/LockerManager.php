<?php

namespace DarkGhostHunter\Laralocker;

use DarkGhostHunter\Laralocker\Contracts\Lockable as LockableContract;
use Illuminate\Contracts\Cache\Repository;

class LockerManager
{
    /**
     * The Cache to use with the Locks
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $repository;

    /**
     * Default prefix to add to the reservations
     *
     * @var string
     */
    protected $prefix;

    /**
     * Default time to live for the reservations
     *
     * @var int
     */
    protected $ttl;

    /**
     * Create a new Locker Manager instance.
     *
     * @param \Illuminate\Contracts\Cache\Repository $repository
     * @param string $prefix
     * @param int $ttl
     * @return void
     */
    public function __construct(Repository $repository, string $prefix, int $ttl)
    {
        $this->repository = $repository;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    /**
     * Instances a new Locker object depending on the Job configuration
     *
     * @param $instance
     * @return \DarkGhostHunter\Laralocker\Locker
     */
    protected function instanceLocker($instance)
    {
        return new Locker(
            $instance,
            $this->useCache($instance),
            $this->usePrefix($instance),
            $this->useReservationTtl($instance)
        );
    }

    /**
     * Locks the Job slot
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $instance
     * @return void
     */
    public function lockSlot(LockableContract $instance)
    {
        $this->instanceLocker($instance)->reserveNextAvailableSlot();
    }

    /**
     * Releases the Job slot from for locking system and updates the last slot
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $instance
     * @return void
     */
    public function releaseSlot(LockableContract $instance)
    {
        $this->instanceLocker($instance)->handleSlotRelease();
    }

    /**
     * Clears the Job reserved slot
     *
     * @param \DarkGhostHunter\Laralocker\Contracts\Lockable $instance
     * @return void
     */
    public function clearSlot(LockableContract $instance)
    {
        $this->instanceLocker($instance)->releaseSlot();
    }

    /**
     * Returns the Cache to use with the Job
     *
     * @param $instance
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function useCache($instance)
    {
        return method_exists($instance, 'cache') ? $instance->cache() : $this->repository;
    }

    /**
     * Return the Job maximum time to live before timing out
     *
     * @param $instance
     * @return int
     */
    protected function useReservationTtl($instance)
    {
        return $instance->slotTtl
            ?? $instance->timeout
            ?? (method_exists($instance, 'retryUntil') ? $instance->retryUntil() : $this->ttl);
    }

    /**
     * Return the prefix to use with the Locker
     *
     * @param $instance
     * @return string
     */
    protected function usePrefix($instance)
    {
        return $instance->prefix ?? $this->prefix;
    }
}
