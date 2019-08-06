<?php

namespace DarkGhostHunter\Laralocker;

use Illuminate\Contracts\Cache\Repository;

class Locker
{
    /**
     * Cache Repository
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $repository;

    /**
     * Queued Lockable Job instance
     *
     * @var \DarkGhostHunter\Laralocker\Contracts\Lockable
     */
    protected $instance;

    /**
     * Prefix to use in the Cache Repository
     *
     * @var string
     */
    protected $prefix;

    /**
     * Slot Reservation Time to Live
     *
     * @var int
     */
    protected $ttl;

    /**
     * Creates a new Concurrent instance
     *
     * @param $instance
     * @param \Illuminate\Contracts\Cache\Repository $repository
     * @param string $prefix
     * @param int $ttl
     */
    public function __construct($instance, Repository $repository, string $prefix, int $ttl)
    {
        $this->instance = $instance;
        $this->repository = $repository;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    /**
     * Handles the release of the slot
     *
     * @return void
     */
    public function handleSlotRelease()
    {
        $this->updateInitialSlot();
        $this->releaseSlot();
    }

    /**
     * Updates the initial Slot so next Jobs can start reserving from there
     *
     * @return void
     */
    protected function updateInitialSlot()
    {
        // If the slot was reserved before the time were we updated the last slot used, then we will
        // update it. This will allow next Jobs to use get the next slot from the last used instead
        // of the very beginning, and block the next jobs from overriding it with a previous slot.
        if ($this->lastSlotTime() < $this->reservedSlotTime()) {
            $this->repository->forever($this->prefix . ':microtime', microtime(true));
            $this->repository->forever($this->prefix . ':last_slot', $this->instance->slot);
        }
    }

    /**
     * Return when was saved the last slot
     *
     * @return int
     */
    protected function lastSlotTime()
    {
        return $this->repository->get($this->prefix . ':microtime');
    }

    /**
     * Return the time of the reserved slot
     *
     * @return float
     */
    protected function reservedSlotTime()
    {
        // If we miss the cache, we will assume it expired while the Job was still processing.
        // In that case, returning zero will allow to NOT update the last saved slot because
        // we don't have any guarantee if this Job ended before the last one to update it.
        return $this->repository->get($this->key($this->instance->slot), 0);
    }

    /**
     * Returns the slot key
     *
     * @param $slot
     * @return string
     */
    protected function key($slot)
    {
        return $this->prefix . '|' . $slot;
    }

    /**
     * Deletes the slot used by the Job
     *
     * @return bool
     */
    public function releaseSlot()
    {
        return $this->repository->forget(
            $this->key($this->instance->slot)
        );
    }

    /**
     * Returns the next available slot to use by the Job
     *
     * @return mixed
     */
    public function reserveNextAvailableSlot()
    {
        $slot = $this->initialSlot();

        do {
            $slot = $this->instance->next($slot);
        } while ($this->isReserved($slot));

        return $this->reserveSlot($slot);
    }

    /**
     * Retrieves the initial Slot to start reserving
     *
     * @return mixed
     */
    protected function initialSlot()
    {
        return $this->repository->remember($this->keyPrefix() . ':last_slot', null, function () {
            return $this->instance->startFrom();
        });
    }

    /**
     * Return if the slot has been reserved by other Job
     *
     * @param $slot
     * @return bool
     */
    protected function isReserved($slot)
    {
        return $this->repository->has($this->key($slot));
    }

    /**
     * Reserves the Slot into the Repository
     *
     * @param $slot
     * @return mixed
     */
    protected function reserveSlot($slot)
    {
        $this->repository->put($this->key($slot), microtime(true), $this->reservationTtl());

        return $slot;
    }
}
